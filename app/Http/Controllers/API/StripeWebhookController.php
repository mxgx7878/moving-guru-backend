<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
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
                'customer.subscription.deleted'   => $this->onSubscriptionChange($event->data->object),
                'invoice.paid',
                'invoice.payment_succeeded'       => $this->onInvoicePaid($event->data->object),
                'invoice.payment_failed'          => $this->onInvoiceFailed($event->data->object),
                default                           => Log::info("Unhandled Stripe event: {$event->type}"),
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
        $this->stripe->recordPaymentFromInvoice($invoice);
    }

    protected function onInvoiceFailed($invoice): void
    {
        $payment = $this->stripe->recordPaymentFromInvoice($invoice);
        $payment->forceFill(['status' => 'failed'])->save();

        if ($invoice->subscription) {
            Subscription::where('stripeSubscriptionId', $invoice->subscription)
                ->update(['status' => 'past_due']);
        }
    }
}