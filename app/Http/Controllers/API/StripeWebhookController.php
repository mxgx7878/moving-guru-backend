<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentSucceededNotification;
use App\Notifications\TrialEndingNotification;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(protected StripeService $stripe) {}

    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature failed', ['error' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type, 'id' => $event->id]);

        try {
            match ($event->type) {
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted'        => $this->onSubscriptionChange($event->data->object),
                'customer.subscription.trial_will_end' => $this->onTrialWillEnd($event->data->object),
                'invoice.paid',
                'invoice.payment_succeeded'            => $this->onInvoicePaid($event->data->object),
                'invoice.payment_failed'               => $this->onInvoiceFailed($event->data->object),
                default                                => Log::info("Unhandled Stripe event: {$event->type}"),
            };
        } catch (\Throwable $e) {
            report($e);
            return response('Handler error', 500);
        }

        return response('ok', 200);
    }

    /**
     * Extract subscription ID from a Stripe invoice, supporting both old and
     * new API shapes. Mirrors the same helper in StripeService — duplicated
     * here only because the webhook handler needs it directly without going
     * through the service.
     */
    protected function subscriptionIdFromInvoice($invoice): ?string
    {
        if (!empty($invoice->subscription) && is_string($invoice->subscription)) {
            return $invoice->subscription;
        }

        $parent = $invoice->parent ?? null;
        if ($parent && ($parent->type ?? null) === 'subscription_details') {
            return $parent->subscription_details->subscription ?? null;
        }

        return null;
    }

    protected function onSubscriptionChange($sub): void
    {
        $user = User::where('stripe_customer_id', $sub->customer)->first();
        if (!$user) {
            Log::warning('onSubscriptionChange: no local user', ['customer' => $sub->customer]);
            return;
        }

        $this->stripe->upsertLocalSubscription($user, null, $sub);
    }

    protected function onTrialWillEnd($sub): void
    {
        $user = User::where('stripe_customer_id', $sub->customer)->first();
        if (!$user?->email) {
            Log::info('trial_will_end: no user or email', ['customer' => $sub->customer]);
            return;
        }

        $this->stripe->upsertLocalSubscription($user, null, $sub);

        $local = Subscription::with('plan')
            ->where('stripeSubscriptionId', $sub->id)
            ->first();

        if (!$local) {
            Log::warning('trial_will_end: local sub not found after upsert', ['sub' => $sub->id]);
            return;
        }

        try {
            $user->notify(new TrialEndingNotification($local));
        } catch (\Throwable $e) {
            Log::warning('TrialEndingNotification failed', [
                'userId' => $user->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    protected function onInvoicePaid($invoice): void
    {
        $subscriptionId = $this->subscriptionIdFromInvoice($invoice);


        // Skip $0 trial-start invoices — Stripe creates these automatically when
        // a trial begins. The user gets the TrialStartedNotification instead, so
        // a "Payment confirmed for $0.00" email would be confusing.
        if ((int) ($invoice->amount_paid ?? 0) === 0 && (int) ($invoice->amount_due ?? 0) === 0) {
            Log::info('onInvoicePaid: skipping $0 trial invoice', ['invoice_id' => $invoice->id]);
            return;
        }

        try {
            $payment = $this->stripe->recordPaymentFromInvoice($invoice);
        } catch (\Throwable $e) {
            Log::warning('recordPaymentFromInvoice failed', [
                'error' => $e->getMessage(),
                'invoice_customer' => $invoice->customer ?? null,
            ]);
            return;
        }

        $user = $payment->user;

        if (!$user) {
            Log::warning('onInvoicePaid: $payment->user is NULL', [
                'payment_userId' => $payment->userId,
            ]);
            return;
        }

        if (!$user->email) {
            Log::warning('onInvoicePaid: user exists but no email', ['userId' => $user->id]);
            return;
        }

        try {
            $user->notify(new PaymentSucceededNotification($payment));
        } catch (\Throwable $e) {
            Log::warning('PaymentSucceededNotification failed', [
                'userId' => $user->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    protected function onInvoiceFailed($invoice): void
    {
        $subscriptionId = $this->subscriptionIdFromInvoice($invoice);

        // 1. Record payment as failed
        try {
            $payment = $this->stripe->recordPaymentFromInvoice($invoice);
            $payment->forceFill(['status' => 'failed'])->save();
        } catch (\Throwable $e) {
            Log::warning('recordPaymentFromInvoice (failed) error', ['error' => $e->getMessage()]);
        }

        // 2. Mark subscription as past_due — use new helper for API compatibility
        $sub = null;
        if ($subscriptionId) {
            $sub = Subscription::where('stripeSubscriptionId', $subscriptionId)
                ->with('plan')->first();
            if ($sub) $sub->forceFill(['status' => 'past_due'])->save();
        }

        $reason = $this->extractInvoiceFailureReason($invoice);

        $user = User::where('stripe_customer_id', $invoice->customer)->first();
        if (!$user?->email || !$sub) return;

        try {
            $user->notify(new PaymentFailedNotification($sub, $reason));
        } catch (\Throwable $e) {
            Log::warning('PaymentFailedNotification failed', [
                'userId' => $user->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    protected function extractInvoiceFailureReason($invoice): ?string
    {
        $pi = $invoice->payment_intent ?? null;

        if (is_object($pi) && !empty($pi->last_payment_error?->message)) {
            return $pi->last_payment_error->message;
        }

        if (is_object($pi) && !empty($pi->charges?->data[0]?->outcome?->seller_message)) {
            return $pi->charges->data[0]->outcome->seller_message;
        }

        if (!empty($invoice->last_finalization_error?->message)) {
            return $invoice->last_finalization_error->message;
        }

        return null;
    }
}