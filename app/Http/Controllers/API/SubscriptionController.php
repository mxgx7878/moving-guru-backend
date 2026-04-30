<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\StripeService;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(protected StripeService $stripe) {}

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

        try {
            $sub = $this->stripe->subscribeOrSwap($user, $plan);
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

    /** POST /api/subscription/cancel */
    public function cancel(Request $request)
    {
        $sub = $request->user()->activeSubscription;
        if (!$sub) return ApiResponse::error('No active subscription.', [], 404);

        $this->stripe->cancelAtPeriodEnd($sub);
        return ApiResponse::success('Subscription will cancel at period end', [
            'subscription' => $sub->fresh(['plan']),
        ]);
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