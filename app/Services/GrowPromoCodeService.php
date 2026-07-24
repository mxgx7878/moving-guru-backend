<?php

namespace App\Services;

use App\Models\GrowPromoCode;
use Illuminate\Validation\ValidationException;

/**
 * Validation + discount maths for Grow-only promo codes.
 *
 * These codes are NOT Stripe coupons. Grow listing fees are one-off
 * PaymentIntents, and Stripe does not auto-apply promotion codes to raw
 * PaymentIntents — so we compute the discount here and the caller reduces
 * the intent `amount` before creating it.
 *
 * Redemption policy (per product decision): GLOBAL CAP ONLY. There is no
 * per-user / per-email uniqueness check — only the code's own
 * max_redemptions and expiry gate it.
 */
class GrowPromoCodeService
{
    /**
     * Stripe rejects charges below its per-currency minimum (≈ $0.50 for
     * AUD/USD). A code that would drop a listing below this can't be charged,
     * so we reject it rather than create an un-confirmable intent. Bump this
     * if you ever need a true "free listing" (0-charge) path — that needs a
     * no-Stripe branch and is intentionally out of scope here.
     */
    public const MIN_CHARGE_CENTS = 50;

    public function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * Look up and validate a Grow promo code. Throws ValidationException
     * (422) with a `promoCode` field error on any failure. Returns the model
     * on success.
     */
    public function validate(string $code): GrowPromoCode
    {
        $code = $this->normalise($code);
        $pc   = GrowPromoCode::where('code', $code)->first();

        $fail = fn (string $msg) => throw ValidationException::withMessages(['promoCode' => $msg]);

        if (!$pc)              $fail('Invalid or expired promo code.');
        if (!$pc->is_active)   $fail('Invalid or expired promo code.');
        if ($pc->isExpired())  $fail('Invalid or expired promo code.');
        if ($pc->isMaxedOut()) $fail('This promo code has reached its usage limit.');

        return $pc;
    }

    /**
     * Compute the discount for a base amount (in the smallest currency unit,
     * e.g. cents). Returns [finalCents, discountCents]. Throws if the result
     * is below the minimum chargeable amount.
     *
     * @return array{0:int,1:int}
     */
    public function applyToCents(GrowPromoCode $pc, int $baseCents): array
    {
        $discountCents = $pc->discount_type === 'percent'
            ? (int) round($baseCents * ((float) $pc->discount_value) / 100)
            : (int) round(((float) $pc->discount_value) * 100);

        // Never discount more than the base.
        $discountCents = max(0, min($discountCents, $baseCents));
        $finalCents    = $baseCents - $discountCents;

        if ($finalCents < self::MIN_CHARGE_CENTS) {
            throw ValidationException::withMessages([
                'promoCode' => 'This code reduces the total below the minimum amount we can charge for this listing.',
            ]);
        }

        return [$finalCents, $discountCents];
    }

    /**
     * Display-friendly price breakdown for a base amount (major units).
     *
     * @return array<string,mixed>
     */
    public function preview(GrowPromoCode $pc, float $baseAmount, ?string $currency = null): array
    {
        [$finalCents, $discountCents] = $this->applyToCents($pc, (int) round($baseAmount * 100));

        return [
            'code'            => $pc->code,
            'discountType'    => $pc->discount_type,
            'discountValue'   => (float) $pc->discount_value,
            'originalAmount'  => number_format($baseAmount, 2, '.', ''),
            'discountAmount'  => number_format($discountCents / 100, 2, '.', ''),
            'finalAmount'     => number_format($finalCents / 100, 2, '.', ''),
            'currency'        => strtoupper($currency ?? $pc->currency ?? 'AUD'),
        ];
    }
}
