<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\StripeService;
use App\Services\PromoCodeService;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(protected StripeService $stripe,protected PromoCodeService $promo,) {}

    /** GET /api/subscription */
    public function show(Request $request)
    {
        $sub = $request->user()
            ->activeSubscription()
            ->with(['plan.planFeatures'])
            ->first();

        if ($sub && $sub->plan) {
            $sub->plan->featureKeys = $sub->plan->planFeatures
                ->pluck('key')->values()->toArray();
        }

        return ApiResponse::success('Current subscription', ['subscription' => $sub]);
    }

    /** POST /api/subscription/setup-intent */
    public function setupIntent(Request $request)
    {
        $intent = $this->stripe->createSetupIntent($request->user());
        return ApiResponse::success('SetupIntent created', $intent);
    }

    /** POST /api/subscription/payment-method */
    public function attachPaymentMethod(Request $request)
    {
        $request->validate(['paymentMethodId' => 'required|string']);
        $this->stripe->setDefaultPaymentMethod($request->user(), $request->paymentMethodId);
        return ApiResponse::success('Card saved');
    }

    /** POST /api/subscription/change  { planId, paymentMethodId? } */
    public function change(Request $request)
    {
        $validated = $request->validate([
            'planId' => [
                'required',
                'string',
                Rule::exists('plans', 'id')->where(fn ($query) => $query->where('isActive', true)),
            ],
            'paymentMethodId' => ['nullable', 'string', 'starts_with:pm_'],
            'promoCode' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $plan = Plan::active()->findOrFail($validated['planId']);

        if (!$plan->stripePriceId) {
            return ApiResponse::error('This plan is not available for checkout.', [], 422);
        }

        try {
            if (!empty($validated['paymentMethodId'])) {
                $this->stripe->setDefaultPaymentMethod($user, $validated['paymentMethodId']);
                $user->refresh();
            }

            if (!$user->default_payment_method_id) {
                return ApiResponse::error('Add a payment method before subscribing.', [], 422);
            }

            $promoCode = null;
            if (!empty($validated['promoCode'])) {
                $promoCode = $this->promo->validateForUser(
                    $user,
                    $validated['promoCode'],
                    $plan,
                );
            }

            $discounts = [];
            if ($plan->hasDiscount && $plan->stripeCouponId) {
                $discounts[] = ['coupon' => $plan->stripeCouponId];
            }
            if ($promoCode?->stripePromotionCodeId) {
                $discounts[] = ['promotion_code' => $promoCode->stripePromotionCodeId];
            }

            $subscription = $this->stripe->subscribeOrSwap($user, $plan, $discounts);

            if ($promoCode) {
                $this->promo->recordRedemption($user, $promoCode, $subscription);
            }

            $subscription->load(['plan.planFeatures']);
            if ($subscription->plan) {
                $subscription->plan->featureKeys = $subscription->plan->planFeatures
                    ->pluck('key')->values()->toArray();
            }

            return ApiResponse::success('Subscription created', [
                'subscription' => $subscription,
                'requiresPaymentConfirmation' => (bool) ($subscription->requiresPaymentConfirmation ?? false),
                'paymentClientSecret' => $subscription->paymentClientSecret ?? null,
                'accessGranted' => in_array($subscription->status, ['active', 'trialing'], true),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error(
                $e->validator->errors()->first('promoCode') ?: 'Invalid promo code.',
                $e->errors(),
                422,
            );
        } catch (\RuntimeException $e) {
            report($e);
            return ApiResponse::error($e->getMessage(), [], 422);
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error('Unable to create the subscription.', [], 500);
        }
    }

    /**
     * POST /api/subscription/cancel
     *
     * Two cancel modes — chosen by subscription state, not the caller:
     *
     *   • Status `trialing`  → immediate cancel. User has paid nothing yet,
     *                          so there's no period to honour. Subscription
     *                          ends now, status → cancelled.
     *   • Status `active`    → cancel at period end. User has paid through
     *                          currentPeriodEnd, so they keep access until
     *                          that date. Can resume any time before then.
     */
    public function cancel(Request $request)
    {
        $sub = $request->user()->activeSubscription;
        if (!$sub) return ApiResponse::error('No active subscription.', [], 404);

        if ($sub->status === 'trialing') {
            $this->stripe->cancelImmediately($sub);
            return ApiResponse::success(
                'Trial cancelled. Your subscription has ended.',
                ['subscription' => $sub->fresh(['plan']), 'immediate' => true],
            );
        }

        $this->stripe->cancelAtPeriodEnd($sub);
        return ApiResponse::success(
            'Subscription will cancel at period end',
            ['subscription' => $sub->fresh(['plan']), 'immediate' => false],
        );
    }

    /** POST /api/subscription/resume */
    public function resume(Request $request)
    {
        $sub = $request->user()->activeSubscription;
        if (!$sub) return ApiResponse::error('No subscription to resume.', [], 404);

        $this->stripe->resumeSubscription($sub);
        return ApiResponse::success('Subscription resumed', [
            'subscription' => $sub->fresh(['plan']),
        ]);
    }

    public function paymentMethod(Request $request)
    {
        try {
            $paymentMethods = $this->stripe->getPaymentMethods(
                $request->user(),
            );
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error('Unable to load the saved card.', [], 422);
        }

        $defaultPaymentMethod = collect($paymentMethods)
            ->firstWhere('isDefault', true);

        return ApiResponse::success('Payment methods', [
            // Keep the singular field temporarily for older frontend builds.
            'paymentMethod' => $defaultPaymentMethod,
            'paymentMethods' => $paymentMethods,
            'defaultPaymentMethodId' => $request->user()->default_payment_method_id,
        ]);
    }

    public function retryPayment(Request $request)
    {
        $validated = $request->validate([
            'paymentMethodId' => [
                'required',
                'string',
                'starts_with:pm_',
            ],
        ]);

        $user = $request->user();

        $subscription = $user->subscriptions()
            ->where('status', 'past_due')
            ->latest()
            ->first();

        if (!$subscription) {
            return ApiResponse::error(
                'No past-due subscription was found.',
                [],
                404,
            );
        }

        try {
            $result = $this->stripe->preparePastDuePaymentRetry(
                $user,
                $subscription,
                $validated['paymentMethodId'],
            );

            return ApiResponse::success(
                'Payment method updated. Complete payment confirmation.',
                $result,
            );
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::error(
                'Unable to retry the subscription payment.',
                [],
                422,
            );
        }
    }
}