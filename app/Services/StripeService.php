<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\TrialStartedNotification;
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

    public function setDefaultPaymentMethod(
    User $user,
    string $paymentMethodId
    ): void {
        $customerId = $this->getOrCreateCustomer($user);

        $paymentMethod = $this->stripe->paymentMethods->retrieve(
            $paymentMethodId
        );

        // Prevent using another customer's card.
        if (
            $paymentMethod->customer
            && $paymentMethod->customer !== $customerId
        ) {
            throw new \RuntimeException(
                'This payment method belongs to another customer.'
            );
        }

        // Attach a newly created PaymentMethod to the customer.
        if (!$paymentMethod->customer) {
            $this->stripe->paymentMethods->attach(
                $paymentMethodId,
                [
                    'customer' => $customerId,
                ],
            );
        }

        // Update Customer default payment method.
        $this->stripe->customers->update(
            $customerId,
            [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ],
        );

        // Update active Subscription default payment method.
        $activeSubscription = $user->activeSubscription;

        if ($activeSubscription?->stripeSubscriptionId) {
            $this->stripe->subscriptions->update(
                $activeSubscription->stripeSubscriptionId,
                [
                    'default_payment_method' => $paymentMethodId,
                ],
            );
        }

        // Keep the local database synchronized.
        $user->forceFill([
            'default_payment_method_id' => $paymentMethodId,
        ])->save();
    }

    // ───────────────────────── Subscriptions ─────────────────────

    public function subscribeOrSwap(User $user, Plan $plan, array $discounts): Subscription
    {
        $customerId = $this->getOrCreateCustomer($user);

        // Reuse an unfinished checkout instead of creating duplicate Stripe
        // subscriptions when the user refreshes or clicks subscribe twice.
        $pending = $user->subscriptions()
            ->where('status', 'incomplete')
            ->latest()
            ->first();

        if ($pending?->stripeSubscriptionId) {
            try {
                $pendingStripeSub = $this->stripe->subscriptions->retrieve(
                    $pending->stripeSubscriptionId,
                    ['expand' => ['latest_invoice.confirmation_secret']],
                );
            } catch (\Throwable $e) {
                $pending->forceFill(['status' => 'cancelled', 'cancelledAt' => now()])->save();
                $pendingStripeSub = null;
            }

           if (
                $pendingStripeSub
                && $pending->planId === $plan->id
                && $pendingStripeSub->status === 'incomplete'
            ) {
                    $pendingStripeSub = $this->stripe->subscriptions->update(
                        $pendingStripeSub->id,
                        [
                            'default_payment_method' => $user->default_payment_method_id,
                        ],
                    );

                    $pendingStripeSub = $this->stripe->subscriptions->retrieve(
                        $pendingStripeSub->id,
                        [
                            'expand' => [
                                'latest_invoice.confirmation_secret',
                                'latest_invoice.payment_intent',
                            ],
                        ],
                    );

                    return $this->withPaymentConfirmationData(
                        $this->upsertLocalSubscription(
                            $user,
                            $plan,
                            $pendingStripeSub,
                        ),
                        $pendingStripeSub,
                    );
                }

            // A different selection replaces the abandoned incomplete attempt.
            if ($pendingStripeSub?->status === 'incomplete') {
                $this->stripe->subscriptions->cancel($pendingStripeSub->id);
                $pending->forceFill(['status' => 'cancelled', 'cancelledAt' => now()])->save();
            }
        }

        $existing   = $user->activeSubscription;


        if ($existing && $existing->stripeSubscriptionId) {
            return $this->swapPlan($existing, $plan, $discounts);
        }


        $params = [
            'customer'         => $customerId,
            'items'            => [['price' => $plan->stripePriceId]],
            'default_payment_method' => $user->default_payment_method_id,
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
                'payment_method_types'        => ['card'],
            ],
            'expand'           => ['latest_invoice.confirmation_secret'],
            'metadata'         => ['userId' => $user->id, 'planId' => $plan->id],
        ];

        if (!empty($discounts)) {
            $params['discounts'] = $discounts;   // ← FIRST invoice discounted
        }


        $trialDays = (int) ($plan->trialPeriodDays ?? 0);
        $isTrial   = $trialDays > 0 && $user->isEligibleForTrial();



        if ($isTrial) {
            $params['trial_period_days'] = $trialDays;
            $params['trial_settings'] = [
                'end_behavior' => ['missing_payment_method' => 'create_invoice'],
            ];
        }

        $stripeSub = $this->stripe->subscriptions->create($params);
        $local     = $this->upsertLocalSubscription($user, $plan, $stripeSub);

        // Free and trial subscriptions become active/trialing immediately.
        // An immediately-paid plan may remain incomplete until Stripe.js
        // confirms 3DS/SCA using this client secret on the checkout page.
        if ($stripeSub->status === 'incomplete') {
            $local = $this->withPaymentConfirmationData($local, $stripeSub);
        } elseif (!in_array($stripeSub->status, ['active', 'trialing'], true)) {
            throw new \RuntimeException($this->extractFailureReason($stripeSub));
        } else {
            $local->setAttribute('requiresPaymentConfirmation', false);
            $local->setAttribute('paymentClientSecret', null);
        }



        if ($isTrial && $stripeSub->status === 'trialing') {
            try {
                \Illuminate\Support\Facades\Log::info('Sending TrialStartedNotification', ['user' => $user, 'subscription' => $local]);
                $user->notify(new TrialStartedNotification($local));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('TrialStartedNotification failed', [
                    'userId' => $user->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $local;
    }

    protected function extractFailureReason(StripeSubscription $sub): string
    {
        $pi = $sub->latest_invoice?->payment_intent ?? null;

        if ($pi?->last_payment_error?->message) {
            return $pi->last_payment_error->message;
        }

        return match ($sub->status) {
            'incomplete'         => 'Payment could not be completed. Please try a different card.',
            'incomplete_expired' => 'Payment timed out. Please try subscribing again.',
            'past_due'           => 'Payment failed. Please update your card and try again.',
            'unpaid'             => 'Payment failed. Please update your card and try again.',
            default              => "Subscription is in {$sub->status} state — please contact support.",
        };
    }

    protected function swapPlan(Subscription $local, Plan $newPlan, array $discounts = []): Subscription
    {
        $stripeSub = $this->stripe->subscriptions->retrieve($local->stripeSubscriptionId);
        $itemId    = $stripeSub->items->data[0]->id;

        $currentPlan = $local->plan;
        $isUpgrade   = $currentPlan
            ? ((float) $newPlan->price > (float) $currentPlan->price)
            : true; // koi current plan nahi mila to safe-side upgrade treat karo

        $params = [
            'cancel_at_period_end' => false,
            'items'                => [['id' => $itemId, 'price' => $newPlan->stripePriceId]],
            'metadata'             => array_merge(
                $stripeSub->metadata ? $stripeSub->metadata->toArray() : [],
                ['planId' => $newPlan->id]
            ),
        ];

        if ($isUpgrade) {
            // Immediate: abhi swap + abhi proration charge
            $params['proration_behavior'] = 'always_invoice';
        } else {
            // Downgrade: abhi kuch charge nahi, naya price agle cycle se effective
            $params['proration_behavior']  = 'none';
            $params['billing_cycle_anchor'] = 'unchanged';
        }

        if (!empty($discounts)) {
            $params['discounts'] = $discounts;
        }

        $updated = $this->stripe->subscriptions->update($local->stripeSubscriptionId, $params);

        // Upgrade pe payment fail ho sakta hai — verify karo
        if ($isUpgrade) {
            $updated = $this->stripe->subscriptions->retrieve($updated->id, [
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            if (!in_array($updated->status, ['active', 'trialing'])) {
                $reason = $this->extractFailureReason($updated);
                $this->upsertLocalSubscription($local->user, $newPlan, $updated);
                throw new \RuntimeException($reason);
            }
        }

        return $this->upsertLocalSubscription($local->user, $newPlan, $updated);
    }

    /**
     * Schedule cancellation at the end of the current billing period.
     * User keeps access until currentPeriodEnd, then sub ends.
     * Can be undone via resumeSubscription() any time before that date.
     */
    public function cancelAtPeriodEnd(Subscription $local): Subscription
    {
        $this->stripe->subscriptions->update($local->stripeSubscriptionId, [
            'cancel_at_period_end' => true,
        ]);

        $local->forceFill(['cancelAtPeriodEnd' => true])->save();
        return $local;
    }

    /**
     * Cancel immediately — used for trialing subscriptions where no payment
     * has been collected yet. Subscription ends right now in Stripe, local
     * row is updated to status 'cancelled'. Cannot be resumed; user must
     * subscribe fresh, and (per business rule) won't get another trial since
     * `has_used_trial` is already set on their user record.
     */
    public function cancelImmediately(Subscription $local): Subscription
    {
        $stripeSub = $this->stripe->subscriptions->cancel($local->stripeSubscriptionId);

        $local->forceFill([
            'status'             => 'cancelled',
            'cancelAtPeriodEnd'  => false,
            'cancelledAt'        => $stripeSub->canceled_at
                ? Carbon::createFromTimestamp($stripeSub->canceled_at)
                : now(),
        ])->save();

        $this->syncUserAccessStatus($local->user, 'cancelled');

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

    // ───────────────────────── Local sync helpers ──

    public function upsertLocalSubscription(User $user, ?Plan $plan, StripeSubscription $sub): Subscription
    {
        $planId = $plan?->id
            ?? optional(Plan::where('stripePriceId', $sub->items->data[0]->price->id ?? null)->first())->id
            ?? $sub->metadata->planId
            ?? null;

        $item          = $sub->items->data[0] ?? null;
        $periodStartTs = $item->current_period_start ?? $sub->current_period_start ?? null;
        $periodEndTs   = $item->current_period_end   ?? $sub->current_period_end   ?? null;

        $status = match ($sub->status) {
            'canceled' => 'cancelled',
            default    => $sub->status,
        };

        if ($status === 'trialing' && !$user->has_used_trial) {
            $user->forceFill(['has_used_trial' => true])->save();
        }

        $local = Subscription::updateOrCreate(
            ['stripeSubscriptionId' => $sub->id],
            [
                'userId'             => $user->id,
                'planId'             => $planId,
                'status'             => $status,
                'currentPeriodStart' => $periodStartTs ? Carbon::createFromTimestamp($periodStartTs) : null,
                'currentPeriodEnd'   => $periodEndTs   ? Carbon::createFromTimestamp($periodEndTs)   : null,
                'cancelAtPeriodEnd'  => (bool) $sub->cancel_at_period_end,
                'cancelledAt'        => $sub->canceled_at ? Carbon::createFromTimestamp($sub->canceled_at) : null,
                'trialEndsAt'        => $sub->trial_end   ? Carbon::createFromTimestamp($sub->trial_end)   : null,
            ]
        );

        $this->syncUserAccessStatus($user, $status);

        return $local;
    }

    protected function withPaymentConfirmationData(
        Subscription $local,
        StripeSubscription $stripeSub,
    ): Subscription {
        $clientSecret = $stripeSub->latest_invoice?->confirmation_secret?->client_secret
            ?? $stripeSub->latest_invoice?->payment_intent?->client_secret;

        if (!$clientSecret) {
            throw new \RuntimeException($this->extractFailureReason($stripeSub));
        }

        $local->setAttribute('requiresPaymentConfirmation', true);
        $local->setAttribute('paymentClientSecret', $clientSecret);

        return $local;
    }

    /**
     * Keep platform access aligned with Stripe while preserving administrative
     * suspension/rejection states. Trialing and active subscriptions have
     * access; all payment/cancellation states return the user to checkout.
     */
    public function syncUserAccessStatus(User $user, string $subscriptionStatus): void
    {
        if (in_array($user->status, ['suspended', 'rejected'], true)) {
            return;
        }

        $hasAccess = in_array($subscriptionStatus, ['active', 'trialing'], true)
            || $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->exists();

        $status = $hasAccess ? 'active' : 'pending_payment';

        if ($user->status !== $status) {
            $user->forceFill(['status' => $status])->save();
        }
    }

    protected function extractSubscriptionIdFromInvoice(StripeInvoice $invoice): ?string
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

    public function recordPaymentFromInvoice(StripeInvoice $invoice): Payment
    {
        $user = User::where('stripe_customer_id', $invoice->customer)->first();
        if (!$user) {
            throw new \RuntimeException("No local user for Stripe customer {$invoice->customer}");
        }

        $subscriptionId = $this->extractSubscriptionIdFromInvoice($invoice);

        $localSub = $subscriptionId
            ? Subscription::where('stripeSubscriptionId', $subscriptionId)->first()
            : null;

        $status = match ($invoice->status) {
            'paid'          => 'paid',
            'uncollectible' => 'failed',
            'void'          => 'failed',
            default         => 'pending',
        };

        $description = $invoice->lines->data[0]->description ?? 'Subscription';
        if ($status === 'failed' && !empty($invoice->last_finalization_error->message)) {
            $description .= ' — ' . $invoice->last_finalization_error->message;
        }

        return Payment::updateOrCreate(
            ['stripeInvoiceId' => $invoice->id],
            [
                'userId'                => $user->id,
                'subscriptionId'        => $localSub?->id,
                'stripePaymentIntentId' => is_string($invoice->payment_intent ?? null)
                    ? $invoice->payment_intent
                    : ($invoice->payment_intent?->id ?? null),
                'amount'                => $invoice->amount_paid > 0
                    ? $invoice->amount_paid / 100
                    : $invoice->amount_due / 100,
                'currency'              => strtoupper($invoice->currency),
                'status'                => $status,
                'paidAt'                => $invoice->status_transitions?->paid_at
                    ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)
                    : null,
                'description'           => $description,
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
            'metadata'    => [
                'planId'          => $plan->id,
                'trialPeriodDays' => (string) ($plan->trialPeriodDays ?? 0),
            ],
        ]);

        $price = $this->createStripePrice($plan, $product->id);

        $plan->forceFill([
            'stripeProductId' => $product->id,
            'stripePriceId'   => $price->id,
        ])->save();

        return $plan;
    }

    public function syncPlanToStripe(\App\Models\Plan $plan): \App\Models\Plan
    {
        if (!$plan->stripeProductId) {
            return $this->createPlanInStripe($plan);
        }

        $this->stripe->products->update($plan->stripeProductId, [
            'name'        => $plan->name,
            'description' => $plan->description ?: null,
            'active'      => (bool) $plan->isActive,
            'metadata'    => [
                'planId'          => $plan->id,
                'trialPeriodDays' => (string) ($plan->trialPeriodDays ?? 0),
            ],
        ]);

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

            if ($plan->stripePriceId) {
                try {
                    $this->stripe->prices->update($plan->stripePriceId, ['active' => false]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $plan->forceFill(['stripePriceId' => $newPrice->id])->save();
        }

        return $plan->refresh();
    }


     public function syncPlanCoupon(Plan $plan): void
    {
        // No discount → drop any existing coupon.
        if (!$plan->hasDiscount) {
            if ($plan->stripeCouponId) {
                try { $this->stripe->coupons->delete($plan->stripeCouponId); }
                catch (\Throwable $e) { report($e); }
                $plan->forceFill(['stripeCouponId' => null])->save();
            }
            return;
        }

        $params = [
            'duration' => in_array($plan->discountDuration, ['once', 'repeating', 'forever'], true)
                ? $plan->discountDuration
                : 'forever',
            'name'     => substr($plan->name . ' discount', 0, 40),
            'metadata' => ['planId' => $plan->id],
        ];

        if ($params['duration'] === 'repeating') {
            $params['duration_in_months'] = (int) ($plan->discountMonths ?: 1);
        }

        if ($plan->discountType === 'percent') {
            $params['percent_off'] = round((float) $plan->discountValue, 2);
        } else {
            $params['amount_off'] = (int) round((float) $plan->discountValue * 100);
            $params['currency']   = strtolower($plan->currency);
        }

        $old = $plan->stripeCouponId;

        try {
            $coupon = $this->stripe->coupons->create($params);
            $plan->forceFill(['stripeCouponId' => $coupon->id])->save();
        } catch (\Throwable $e) {
            report($e);
            return; // keep old coupon if create failed
        }

        if ($old) {
            try { $this->stripe->coupons->delete($old); }
            catch (\Throwable $e) { report($e); }
        }
    }

    /**
     * Attach (or remove) the plan's coupon on a single Stripe subscription.
     */
    public function syncSubscriptionDiscount(Subscription $sub, ?Plan $plan = null): void
    {
        if (!$sub->stripeSubscriptionId) return;
        $plan = $plan ?: $sub->plan;
        if (!$plan) return;

        try {
            if ($plan->hasDiscount && $plan->stripeCouponId) {
                $this->stripe->subscriptions->update($sub->stripeSubscriptionId, [
                    'discounts' => [['coupon' => $plan->stripeCouponId]],
                ]);
            } else {
                // Empty string clears all discounts on the subscription.
                $this->stripe->subscriptions->update($sub->stripeSubscriptionId, [
                    'discounts' => '',
                ]);
            }
        } catch (\Throwable $e) {
            report($e); // e.g. deleteDiscount when none exists — harmless
        }
    }

    /**
     * Apply/refresh the plan discount across all live subscribers of a plan.
     * Called after an admin edits a plan's discount.
     */
    public function applyPlanDiscountToActiveSubscribers(Plan $plan): void
    {
        Subscription::where('planId', $plan->id)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->whereNotNull('stripeSubscriptionId')
            ->get()
            ->each(fn (Subscription $s) => $this->syncSubscriptionDiscount($s, $plan));
    }


    public function syncAllPlansFromStripe(): array
    {
        $synced  = 0;
        $skipped = 0;

        $iterators = [
            $this->stripe->products->all(['limit' => 100, 'active' => true ])->autoPagingIterator(),
            $this->stripe->products->all(['limit' => 100, 'active' => false])->autoPagingIterator(),
        ];

        foreach ($iterators as $products) {
            foreach ($products as $product) {
                $planId = $product->metadata->planId
                    ?? \App\Models\Plan::where('stripeProductId', $product->id)->value('id');

                if (!$planId) { $skipped++; continue; }

                $prices = $this->stripe->prices->all([
                    'product' => $product->id,
                    'active'  => true,
                    'limit'   => 10,
                ]);

                $price = collect($prices->data)->first(fn ($p) => !empty($p->recurring));

                $attrs = [
                    'name'            => $product->name,
                    'description'     => $product->description,
                    'isActive'        => (bool) $product->active,
                    'stripeProductId' => $product->id,
                ];

                if (isset($product->metadata->trialPeriodDays)) {
                    $attrs['trialPeriodDays'] = (int) $product->metadata->trialPeriodDays;
                }

                if ($price) {
                    $attrs += [
                        'price'         => $price->unit_amount / 100,
                        'currency'      => strtoupper($price->currency),
                        'interval'      => $price->recurring->interval,
                        'intervalCount' => $price->recurring->interval_count,
                        'stripePriceId' => $price->id,
                    ];
                }

                \App\Models\Plan::updateOrCreate(['id' => $planId], $attrs);
                $synced++;
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped];
    }

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

      public function createPromoCodeInStripe(\App\Models\PromoCode $pc): void
    {
        $couponParams = [
            'duration' => $pc->duration ?: 'once',
            'name'     => substr('Promo ' . $pc->code, 0, 40),
            'metadata' => ['promoCode' => $pc->code],
        ];
        if ($pc->duration === 'repeating') {
            $couponParams['duration_in_months'] = (int) ($pc->durationInMonths ?: 1);
        }
        if ($pc->discountType === 'percent') {
            $couponParams['percent_off'] = round((float) $pc->discountValue, 2);
        } else {
            $couponParams['amount_off'] = (int) round((float) $pc->discountValue * 100);
            $couponParams['currency']   = strtolower($pc->currency ?: 'usd');
        }

        $coupon = $this->stripe->coupons->create($couponParams);

        $promoParams = [
            'promotion' => ['type' => 'coupon', 'coupon' => $coupon->id],
            'code'     => $pc->code,
            'active'   => (bool) $pc->isActive,
            'metadata' => ['promoCodeId' => (string) $pc->id],
        ];
        if ($pc->maxRedemptions) $promoParams['max_redemptions'] = (int) $pc->maxRedemptions;
        if ($pc->expiresAt)      $promoParams['expires_at']      = $pc->expiresAt->timestamp;

        $promo = $this->stripe->promotionCodes->create($promoParams);

        $pc->forceFill([
            'stripeCouponId'        => $coupon->id,
            'stripePromotionCodeId' => $promo->id,
        ])->save();
    }

    /** Toggle a promo code active/inactive in Stripe (the only mutable field). */
    public function setPromoCodeActiveInStripe(\App\Models\PromoCode $pc): void
    {
        if (!$pc->stripePromotionCodeId) return;
        try {
            $this->stripe->promotionCodes->update($pc->stripePromotionCodeId, [
                'active' => (bool) $pc->isActive,
            ]);
        } catch (\Throwable $e) { report($e); }
    }

    public function archivePromoCodeInStripe(\App\Models\PromoCode $pc): void
    {
        // Promo codes can't be deleted — deactivate. The coupon can be deleted.
        if ($pc->stripePromotionCodeId) {
            try {
                $this->stripe->promotionCodes->update($pc->stripePromotionCodeId, ['active' => false]);
            } catch (\Throwable $e) { report($e); }
        }
        if ($pc->stripeCouponId) {
            try { $this->stripe->coupons->delete($pc->stripeCouponId); }
            catch (\Throwable $e) { report($e); }
        }
    }

   public function applyPromoToSubscription(Subscription $sub, \App\Models\PromoCode $pc): void
    {
        if (!$sub->stripeSubscriptionId) return;

        $discounts = [];

        // Keep the plan's own discount...
        $plan = $sub->plan;
        if ($plan && $plan->hasDiscount && $plan->stripeCouponId) {
            $discounts[] = ['coupon' => $plan->stripeCouponId];
        }

        // ...then stack the promo on top.
        $discounts[] = ['promotion_code' => $pc->stripePromotionCodeId];

        $this->stripe->subscriptions->update($sub->stripeSubscriptionId, [
            'discounts' => $discounts,
        ]);
    }

 public function recurringChargeAfterDiscounts(Subscription $local, ?Plan $plan = null): ?float
    {
        $plan = $plan ?: $local->plan;
        if (!$plan) {
            \Log::info('[CHARGE-DEBUG] no plan, returning null');
            return null;
        }

        $amount = (float) $plan->price;   // start from list price (60.00)

        if (!$local->stripeSubscriptionId) {
            \Log::info('[CHARGE-DEBUG] no stripe sub id, returning plan price', ['amount' => $amount]);
            return round($amount, 2);
        }

        try {
            $sub = $this->stripe->subscriptions->retrieve($local->stripeSubscriptionId, [
                'expand' => ['discounts'],
            ]);


 foreach (($sub->discounts ?? []) as $discount) {
                // New Stripe API: coupon id sits at discount.source.coupon (a string).
                $couponId = $discount->source->coupon
                    ?? $discount->coupon            // older shape fallback
                    ?? null;

                if (!$couponId) continue;

                // Retrieve the coupon to read its percent_off / amount_off.
                try {
                    $coupon = is_string($couponId)
                        ? $this->stripe->coupons->retrieve($couponId)
                        : $couponId;
                } catch (\Throwable $e) {
                    \Log::warning('[CHARGE-DEBUG] coupon retrieve failed', [
                        'couponId' => $couponId, 'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                if (!empty($coupon->percent_off)) {
                    $amount -= $amount * ((float) $coupon->percent_off / 100);
                } elseif (!empty($coupon->amount_off)) {
                    $amount -= ((float) $coupon->amount_off) / 100;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[CHARGE-DEBUG] FAILED', [
                'sub'   => $local->stripeSubscriptionId,
                'error' => $e->getMessage(),
            ]);
            return (float) ($plan->discountedPrice ?? $plan->price);
        }

        $final = round(max(0, $amount), 2);
        return $final;
    }
       public function getPaymentMethods(User $user): array
    {
        if (!$user->stripe_customer_id) {
            return [];
        }

        $customer = $this->stripe->customers->retrieve($user->stripe_customer_id);
        $stripeDefault = $customer->invoice_settings?->default_payment_method;
        $defaultPaymentMethodId = is_string($stripeDefault)
            ? $stripeDefault
            : (is_object($stripeDefault)
                ? ($stripeDefault->id ?? $user->default_payment_method_id)
                : $user->default_payment_method_id);

        // Keep the local shortcut synchronized if the default was changed
        // directly from Stripe Dashboard.
        if ($defaultPaymentMethodId !== $user->default_payment_method_id) {
            $user->forceFill([
                'default_payment_method_id' => $defaultPaymentMethodId,
            ])->save();
        }

        $paymentMethods = $this->stripe->paymentMethods->all([
            'customer' => $user->stripe_customer_id,
            'type' => 'card',
            'limit' => 100,
        ]);

        return collect($paymentMethods->data)
            ->filter(fn ($paymentMethod) => $paymentMethod->card)
            ->map(fn ($paymentMethod) => [
                'id' => $paymentMethod->id,
                'brand' => $paymentMethod->card->brand,
                'last4' => $paymentMethod->card->last4,
                'expMonth' => $paymentMethod->card->exp_month,
                'expYear' => $paymentMethod->card->exp_year,
                'isDefault' => $paymentMethod->id === $defaultPaymentMethodId,
            ])
            ->sortByDesc('isDefault')
            ->values()
            ->all();
    }

    public function preparePastDuePaymentRetry(
    User $user,
    Subscription $localSubscription,
    string $paymentMethodId,
    ): array {
        // This updates:
        // 1. Customer default
        // 2. Subscription default
        // 3. Local user default_payment_method_id
        $this->setDefaultPaymentMethod(
            $user,
            $paymentMethodId,
        );

        $stripeSubscription = $this->stripe->subscriptions->retrieve(
            $localSubscription->stripeSubscriptionId,
            [
                'expand' => [
                    'latest_invoice.confirmation_secret',
                    'latest_invoice.payment_intent',
                ],
            ],
        );

        $invoice = $stripeSubscription->latest_invoice;

        if (!$invoice) {
            throw new \RuntimeException(
                'No renewal invoice was found.'
            );
        }

        if ($invoice->status === 'paid') {
            $this->upsertLocalSubscription(
                $user,
                $localSubscription->plan,
                $stripeSubscription,
            );

            return [
                'requiresPaymentConfirmation' => false,
                'accessGranted' => true,
            ];
        }

        $clientSecret =
            $invoice->confirmation_secret?->client_secret
            ?? $invoice->payment_intent?->client_secret
            ?? null;

        if (!$clientSecret) {
            throw new \RuntimeException(
                'The failed invoice cannot be retried.'
            );
        }

        return [
            'subscriptionId' => $localSubscription->id,
            'requiresPaymentConfirmation' => true,
            'paymentClientSecret' => $clientSecret,
            'paymentMethodId' => $paymentMethodId,
            'accessGranted' => false,
        ];
    }
}