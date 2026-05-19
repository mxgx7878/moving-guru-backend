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

    public function setDefaultPaymentMethod(User $user, string $paymentMethodId): void
    {
        $customerId = $this->getOrCreateCustomer($user);

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

    public function subscribeOrSwap(User $user, Plan $plan): Subscription
    {
        $customerId = $this->getOrCreateCustomer($user);
        $existing   = $user->activeSubscription;

        if ($existing && $existing->stripeSubscriptionId) {
            return $this->swapPlan($existing, $plan);
        }

        $params = [
            'customer'         => $customerId,
            'items'            => [['price' => $plan->stripePriceId]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
                'payment_method_types'        => ['card'],
            ],
            'expand'           => ['latest_invoice'],
            'metadata'         => ['userId' => $user->id, 'planId' => $plan->id],
        ];

        $trialDays = (int) ($plan->trialPeriodDays ?? 0);
        $isTrial   = $trialDays > 0 && $user->isEligibleForTrial();

        if ($isTrial) {
            $params['trial_period_days'] = $trialDays;
            $params['trial_settings'] = [
                'end_behavior' => ['missing_payment_method' => 'create_invoice'],
            ];
        }

        $stripeSub = $this->stripe->subscriptions->create($params);

        $this->payFirstInvoice($stripeSub, $user);

        $stripeSub = $this->stripe->subscriptions->retrieve($stripeSub->id, [
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        if (!in_array($stripeSub->status, ['active', 'trialing'])) {
            $reason = $this->extractFailureReason($stripeSub);
            $this->upsertLocalSubscription($user, $plan, $stripeSub);
            throw new \RuntimeException($reason);
        }

        $local = $this->upsertLocalSubscription($user, $plan, $stripeSub);

        if ($isTrial && $stripeSub->status === 'trialing') {
            try {
                $user->notify(new TrialStartedNotification($local));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('TrialStartedNotification failed', [
                    'userId' => $user->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $local;
    }

    protected function payFirstInvoice(\Stripe\Subscription $sub, User $user): void
    {
        $invoice = $sub->latest_invoice;

        if (!$invoice) {
            \Illuminate\Support\Facades\Log::warning('No invoice on new subscription', ['sub' => $sub->id]);
            return;
        }

        if ($invoice->status === 'paid') return;
        if ($invoice->amount_due === 0)  return;

        try {
            $paid = $this->stripe->invoices->pay($invoice->id, [
                'payment_method' => $user->default_payment_method_id,
            ]);

            \Illuminate\Support\Facades\Log::info('Invoice pay result', [
                'invoice_id'  => $paid->id,
                'status'      => $paid->status,
                'amount_paid' => $paid->amount_paid,
            ]);
        } catch (\Stripe\Exception\CardException $e) {
            $err = $e->getError();
            \Illuminate\Support\Facades\Log::warning('CardException on invoice pay', [
                'code'         => $err->code,
                'decline_code' => $err->decline_code ?? null,
                'message'      => $err->message,
            ]);
            throw new \RuntimeException(
                $err->message ?: 'Your card was declined. Please try a different card.'
            );
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            \Illuminate\Support\Facades\Log::warning('InvalidRequest on invoice pay', [
                'message' => $e->getError()->message ?? $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'Could not process payment: ' . ($e->getError()->message ?? $e->getMessage())
            );
        }
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

        return Subscription::updateOrCreate(
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
}