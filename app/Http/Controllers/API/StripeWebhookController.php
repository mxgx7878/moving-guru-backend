<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentSucceededNotification;
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

        try {
            match ($event->type) {
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted' => $this->onSubscriptionChange($event->data->object),
                'invoice.paid',
                'invoice.payment_succeeded'     => $this->onInvoicePaid($event->data->object),
                'invoice.payment_failed'        => $this->onInvoiceFailed($event->data->object),
                default                         => Log::info("Unhandled Stripe event: {$event->type}"),
            };
        } catch (\Throwable $e) {
            report($e);
            return response('Handler error', 500);
        }

        return response('ok', 200);
    }

    protected function onSubscriptionChange($sub): void
    {
        $user = User::where('stripe_customer_id', $sub->customer)->first();
        if (!$user) return;

        $this->stripe->upsertLocalSubscription($user, null, $sub);
    }

    protected function onInvoicePaid($invoice): void
    {
        try {
            $payment = $this->stripe->recordPaymentFromInvoice($invoice);
        } catch (\Throwable $e) {
            Log::warning('recordPaymentFromInvoice failed', ['error' => $e->getMessage()]);
            return;
        }

        $user = $payment->user;
        if (!$user?->email) return;

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
        // 1. Record payment as failed
        try {
            $payment = $this->stripe->recordPaymentFromInvoice($invoice);
            $payment->forceFill(['status' => 'failed'])->save();
        } catch (\Throwable $e) {
            Log::warning('recordPaymentFromInvoice (failed) error', ['error' => $e->getMessage()]);
        }

        // 2. Mark subscription as past_due
        $sub = null;
        if ($invoice->subscription) {
            $sub = Subscription::where('stripeSubscriptionId', $invoice->subscription)
                ->with('plan')->first();
            if ($sub) $sub->forceFill(['status' => 'past_due'])->save();
        }

        // 3. Extract the actual failure reason from Stripe payload
        $reason = $this->extractInvoiceFailureReason($invoice);

        // 4. Send email with the reason
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

    /**
     * Pull a human-readable failure reason from a failed Invoice.
     * Stripe puts it in different places depending on what failed.
     */
    protected function extractInvoiceFailureReason($invoice): ?string
    {
        // Most specific — the PaymentIntent's last error
        $pi = $invoice->payment_intent ?? null;

        if (is_object($pi) && !empty($pi->last_payment_error?->message)) {
            return $pi->last_payment_error->message;
        }

        // Charge-level outcome (e.g. "Your card was declined.")
        if (is_object($pi) && !empty($pi->charges?->data[0]?->outcome?->seller_message)) {
            return $pi->charges->data[0]->outcome->seller_message;
        }

        // Invoice-level error (rare but possible)
        if (!empty($invoice->last_finalization_error?->message)) {
            return $invoice->last_finalization_error->message;
        }

        return null;
    }
}