<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\StripeService;
use App\Services\PromoCodeService;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

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
        $request->validate([
            'planId'          => 'required|string|exists:plans,id',
            'paymentMethodId' => 'nullable|string',
            'promoCode'       => 'nullable|string|max:64',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($request->planId);

        if ($request->paymentMethodId) {
            $this->stripe->setDefaultPaymentMethod($user, $request->paymentMethodId);
            $user->refresh();
        }

        if (!$user->default_payment_method_id) {
            return ApiResponse::error('Add a payment method before subscribing.', [], 422);
        }

        // Pre-validate the promo before creating the subscription.
        $pc = null;
        if ($request->filled('promoCode')) {
            // if ($plan->hasDiscount) {
            //     return ApiResponse::error(
            //         'This plan already has a discount, so a promo code can’t be applied.',
            //         ['promoCode' => ['Promo codes are not allowed on discounted plans.']],
            //         422,
            //     );
            // }
            try {
                $pc = $this->promo->validateForUser($user, $request->promoCode, $plan);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return ApiResponse::error(
                    $e->validator->errors()->first('promoCode') ?: 'Invalid promo code.',
                    $e->errors(), 422,
                );
            }
        }

         $discounts = [];
        if ($plan->hasDiscount && $plan->stripeCouponId) {
            $discounts[] = ['coupon' => $plan->stripeCouponId];
        }
        if ($pc && $pc->stripePromotionCodeId) {
            $discounts[] = ['promotion_code' => $pc->stripePromotionCodeId];
        }


        try {
            $sub = $this->stripe->subscribeOrSwap($user, $plan, $discounts);

            if ($pc) {
                $this->promo->recordRedemption($user, $pc, $sub);   // sirf record, discount already applied
            }

            $sub->load(['plan.planFeatures']);
            if ($sub->plan) {
                $sub->plan->featureKeys = $sub->plan->planFeatures
                    ->pluck('key')->values()->toArray();
            }

            return ApiResponse::success('Plan updated', ['subscription' => $sub]);
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error($e->getMessage(), [], 500);
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
}