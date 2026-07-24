<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Grow-only promo code. Independent of the subscription `PromoCode` model —
 * no Stripe coupon/promotion objects are created for these. The discount is
 * applied by reducing the one-off Grow PaymentIntent amount server-side.
 */
class GrowPromoCode extends Model
{
    protected $table = 'grow_promo_codes';

    protected $fillable = [
        'code', 'discount_type', 'discount_value', 'currency',
        'max_redemptions', 'times_redeemed', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'discount_value'  => 'decimal:2',
        'max_redemptions' => 'integer',
        'times_redeemed'  => 'integer',
        'expires_at'      => 'datetime',
        'is_active'       => 'boolean',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isMaxedOut(): bool
    {
        return $this->max_redemptions !== null
            && $this->times_redeemed >= $this->max_redemptions;
    }
}
