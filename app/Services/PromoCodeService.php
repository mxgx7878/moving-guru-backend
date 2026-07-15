<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Helpers\ApiResponse;

class PromoCodeService
{
    public function __construct(protected StripeService $stripe) {}

    public function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Validate a code for a user. Throws ValidationException (422) with a
     * `promoCode` field error on failure. Returns the PromoCode on success.
     */
    public function validateForUser(User $user, string $code, ?Plan $plan = null): PromoCode
    {
        $code = $this->normalise($code);
        $pc   = PromoCode::where('code', $code)->first();

        $fail = fn (string $msg) => throw ValidationException::withMessages(['promoCode' => $msg]);

        if (!$pc)              $fail('Invalid or expired promo code.');
        if (!$pc->isActive)    $fail('Invalid or expired promo code.');
        if ($pc->isExpired())  $fail('Invalid or expired promo code.');
        if ($pc->isMaxedOut()) $fail('Invalid or expired promo code.');

        $already = PromoCodeRedemption::where('promoCodeId', $pc->id)
            ->where('userId', $user->id)
            ->exists();
        if ($already) $fail('You have already used this promo code.');

        

        return $pc;
    }

    /** Price preview for a plan with this promo applied. */
public function preview(PromoCode $pc, Plan $plan): array
    {
        $original = (float) $plan->price;

        // Stack: start from the plan's already-discounted price.
        $base = $plan->hasDiscount ? (float) $plan->discountedPrice : $original;

        $final = $pc->discountType === 'percent'
            ? $base * (1 - ((float) $pc->discountValue / 100))
            : $base - (float) $pc->discountValue;

        $final = max(0, round($final, 2));

        return [
            'originalPrice'   => number_format($original, 2, '.', ''),
            'discountedPrice' => number_format($final, 2, '.', ''),
            'discountType'    => $pc->discountType,
            'discountValue'   => (float) $pc->discountValue,
            'currency'        => $plan->currency,
        ];
    }

    /**
     * Attach the promo to a Stripe subscription, record the redemption and
     * bump counters. The unique (promoCodeId,userId) index is the final guard.
     */
    public function redeem(User $user, PromoCode $pc, Subscription $sub, ?Plan $plan = null): void
    {
        $this->stripe->applyPromoToSubscription($sub, $pc);

        PromoCodeRedemption::create([
            'promoCodeId'           => $pc->id,
            'userId'                => $user->id,
            'subscriptionId'        => $sub->id,
            'stripePromotionCodeId' => $pc->stripePromotionCodeId,
            'redeemedAt'            => now(),
        ]);

        $pc->increment('timesRedeemed');
    }


    public function recordRedemption(
        User $user,
        PromoCode $promoCode,
        Subscription $subscription,
    ): void {
        $redemption = PromoCodeRedemption::firstOrCreate(
            [
                'promoCodeId' => $promoCode->id,
                'userId' => $user->id,
            ],
            [
                'subscriptionId' => $subscription->id,
                'stripePromotionCodeId' => $promoCode->stripePromotionCodeId,
                'redeemedAt' => now(),
            ],
        );

        if ($redemption->wasRecentlyCreated) {
            $promoCode->increment('timesRedeemed');
        }
    }
}