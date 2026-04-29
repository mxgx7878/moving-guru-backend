<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Stripe\Customer;
use Stripe\Invoice as StripeInvoice;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

class StripeService
{
    protected StripeClient $stripe;
    protected string $currency;

    public function __construct()
    {
        $this->stripe   = new StripeClient(config('services.stripe.secret'));
        $this->currency = config('services.stripe.currency', 'usd');
    }

    // ───────────────────────── Customers ─────────────────────────

    public function getOrCreateCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            try {
                $this->stripe->customers->retrieve($user->stripe_customer_id);
                return $user->stripe_customer_id;
            } catch (\Throwable $e) {
                // fall through and create a new one
            }
        }

        $customer = $this->stripe->customers->create([
            'email'    => $user->email,
            'name'     => $user->name,
            'metadata' => ['userId' => $user->id],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();
        return $customer->id;
    }

    // ───────────────────────── SetupIntent (collect card) ────────

    public function createSetupIntent(User $user): array
    {
        $customerId = $this->getOrCreateCustomer($user);

        $intent = $this->stripe->setupIntents->create([
            'customer'             => $customerId,
            'payment_method_types' => ['card'],
            'usage'                => 'off_session',
            'metadata'             => ['userId' => $user->id],
        ]);

        return [
            'clientSecret' => $intent->client_secret,
            'customerId'   => $customerId,
        ];
    }

    public function setDefaultPaymentMethod(User $user, string $paymentMethodId): void
    {
        $customerId = $this->getOrCreateCustomer($user);

        // Attach if not already attached (safe: PaymentMethod is idempotent if already on customer)
        try {
            $this->stripe->paymentMethods->attach($paymentMethodId, ['customer' => $customerId]);
        } catch (\Throwable $e) {
            // already attached — fine
        }

        $this->stripe->customers->update($customerId, [
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
        ]);

        $user->forceFill(['default_payment_method_id' => $paymentMethodId])->save();
    }

    // ───────────────────────── Subscriptions ─────────────────────

    /**
     * Create OR switch a subscription. Single entry-point — controllers always call this.
     */
    public function subscribeOrSwap(User $user, Plan $plan): Subscription
    {
        $customerId = $this->getOrCreateCustomer($user);
        $existing   = $user->activeSubscription;

        if ($existing && $existing->stripeSubscriptionId) {
            return $this->swapPlan($existing, $plan);
        }

        $stripeSub = $this->stripe->subscriptions->create([
            'customer'           => $customerId,
            'items'              => [['price' => $plan->stripePriceId]],
            'payment_behavior'   => 'default_incomplete',
            'payment_settings'   => ['save_default_payment_method' => 'on_subscription'],
            'expand'             => ['latest_invoice.payment_intent'],
            'metadata'           => ['userId' => $user->id, 'planId' => $plan->id],
        ]);

        return $this->upsertLocalSubscription($user, $plan, $stripeSub);
    }

    protected function swapPlan(Subscription $local, Plan $newPlan): Subscription
    {
        $stripeSub = $this->stripe->subscriptions->retrieve($local->stripeSubscriptionId);
        $itemId    = $stripeSub->items->data[0]->id;

        $updated = $this->stripe->subscriptions->update($local->stripeSubscriptionId, [
            'cancel_at_period_end' => false,
            'proration_behavior'   => 'create_prorations',
            'items'                => [['id' => $itemId, 'price' => $newPlan->stripePriceId]],
            'metadata'             => array_merge((array) $stripeSub->metadata, ['planId' => $newPlan->id]),
        ]);

        return $this->upsertLocalSubscription($local->user, $newPlan, $updated);
    }

    public function cancelAtPeriodEnd(Subscription $local): Subscription
    {
        $this->stripe->subscriptions->update($local->stripeSubscriptionId, [
            'cancel_at_period_end' => true,
        ]);

        $local->forceFill(['cancelAtPeriodEnd' => true])->save();
        return $local;
    }

    public function resumeSubscription(Subscription $local): Subscription
    {
        $this->stripe->subscriptions->update($local->stripeSubscriptionId, [
            'cancel_at_period_end' => false,
        ]);

        $local->forceFill(['cancelAtPeriodEnd' => false, 'cancelledAt' => null])->save();
        return $local;
    }

    // ───────────────────────── Local sync helpers (used by webhook + flows) ──

    public function upsertLocalSubscription(User $user, ?Plan $plan, StripeSubscription $sub): Subscription
    {
        $planId = $plan?->id
            ?? optional(Plan::where('stripePriceId', $sub->items->data[0]->price->id ?? null)->first())->id
            ?? $sub->metadata->planId
            ?? null;

        return Subscription::updateOrCreate(
            ['stripeSubscriptionId' => $sub->id],
            [
                'userId'             => $user->id,
                'planId'             => $planId,
                'status'             => $sub->status,
                'currentPeriodStart' => $sub->current_period_start ? Carbon::createFromTimestamp($sub->current_period_start) : null,
                'currentPeriodEnd'   => $sub->current_period_end   ? Carbon::createFromTimestamp($sub->current_period_end)   : null,
                'cancelAtPeriodEnd'  => (bool) $sub->cancel_at_period_end,
                'cancelledAt'        => $sub->canceled_at ? Carbon::createFromTimestamp($sub->canceled_at) : null,
                'trialEndsAt'        => $sub->trial_end   ? Carbon::createFromTimestamp($sub->trial_end)   : null,
            ]
        );
    }

    public function recordPaymentFromInvoice(StripeInvoice $invoice): Payment
    {
        $user = User::where('stripe_customer_id', $invoice->customer)->first();
        if (!$user) {
            throw new \RuntimeException("No local user for Stripe customer {$invoice->customer}");
        }

        $localSub = $invoice->subscription
            ? Subscription::where('stripeSubscriptionId', $invoice->subscription)->first()
            : null;

        return Payment::updateOrCreate(
            ['stripeInvoiceId' => $invoice->id],
            [
                'userId'                => $user->id,
                'subscriptionId'        => $localSub?->id,
                'stripePaymentIntentId' => $invoice->payment_intent,
                'amount'                => $invoice->amount_paid / 100,
                'currency'              => strtoupper($invoice->currency),
                'status'                => $invoice->status === 'paid' ? 'paid' : 'pending',
                'paidAt'                => $invoice->status_transitions->paid_at
                    ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)
                    : null,
                'description'           => $invoice->lines->data[0]->description ?? 'Subscription',
                'hostedInvoiceUrl'      => $invoice->hosted_invoice_url,
                'invoicePdfUrl'         => $invoice->invoice_pdf,
            ]
        );
    }

    public function createPlanInStripe(\App\Models\Plan $plan): \App\Models\Plan
    {
        if ($plan->stripeProductId) {
            return $this->syncPlanToStripe($plan);
        }

        $product = $this->stripe->products->create([
            'name'        => $plan->name,
            'description' => $plan->description ?: null,
            'metadata'    => ['planId' => $plan->id],
        ]);

        $price = $this->createStripePrice($plan, $product->id);

        $plan->forceFill([
            'stripeProductId' => $product->id,
            'stripePriceId'   => $price->id,
        ])->save();

        return $plan;
    }

    /**
     * Sync a plan's metadata + price to Stripe.
     *
     * - Always updates the Product (name, description, active flag).
     * - If price/currency/interval/intervalCount changed since the last
     *   sync, creates a new Stripe Price, archives the old one, and
     *   points stripePriceId at the new one. Stripe Prices are immutable.
     *
     * Caller passes the plan AFTER mutating it; we detect changes by
     * comparing the active Stripe Price with the plan's current values.
     */
    public function syncPlanToStripe(\App\Models\Plan $plan): \App\Models\Plan
    {
        if (!$plan->stripeProductId) {
            return $this->createPlanInStripe($plan);
        }

        // 1. Always sync product metadata
        $this->stripe->products->update($plan->stripeProductId, [
            'name'        => $plan->name,
            'description' => $plan->description ?: null,
            'active'      => (bool) $plan->isActive,
            'metadata'    => ['planId' => $plan->id],
        ]);

        // 2. Decide whether the Price needs replacing
        $needsNewPrice = true;
        if ($plan->stripePriceId) {
            try {
                $current = $this->stripe->prices->retrieve($plan->stripePriceId);
                $needsNewPrice =
                    ((int) round($plan->price * 100))     !== (int) $current->unit_amount
                    || strtolower($plan->currency)         !== strtolower($current->currency)
                    || $plan->interval                     !== ($current->recurring->interval ?? null)
                    || (int) $plan->intervalCount          !== (int) ($current->recurring->interval_count ?? 1);
            } catch (\Throwable $e) {
                $needsNewPrice = true;
            }
        }

        if ($needsNewPrice) {
            $newPrice = $this->createStripePrice($plan, $plan->stripeProductId);

            // Archive the old price (Stripe doesn't allow delete; archived
            // prices keep working for existing subscriptions).
            if ($plan->stripePriceId) {
                try {
                    $this->stripe->prices->update($plan->stripePriceId, ['active' => false]);
                } catch (\Throwable $e) {
                    report($e); // best-effort
                }
            }

            $plan->forceFill(['stripePriceId' => $newPrice->id])->save();
        }

        return $plan->refresh();
    }

    /**
     * Soft-archive a plan in Stripe. Keeps the row in our DB (subscriptions
     * still reference it) and flips `active=false` on both the Product and
     * Price in Stripe so it can no longer be subscribed to.
     */
    public function archivePlanInStripe(\App\Models\Plan $plan): void
    {
        if ($plan->stripePriceId) {
            try {
                $this->stripe->prices->update($plan->stripePriceId, ['active' => false]);
            } catch (\Throwable $e) { report($e); }
        }

        if ($plan->stripeProductId) {
            try {
                $this->stripe->products->update($plan->stripeProductId, ['active' => false]);
            } catch (\Throwable $e) { report($e); }
        }
    }

    protected function createStripePrice(\App\Models\Plan $plan, string $productId): \Stripe\Price
    {
        return $this->stripe->prices->create([
            'product'     => $productId,
            'currency'    => strtolower($plan->currency),
            'unit_amount' => (int) round($plan->price * 100),
            'recurring'   => [
                'interval'       => $plan->interval,
                'interval_count' => (int) $plan->intervalCount,
            ],
            'metadata'    => ['planId' => $plan->id],
        ]);
    }
}