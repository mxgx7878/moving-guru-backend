<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $table = 'promo_codes';

    protected $fillable = [
        'code', 'discountType', 'discountValue', 'currency',
        'duration', 'durationInMonths',
        'maxRedemptions', 'expiresAt', 'isActive',
        'stripeCouponId', 'stripePromotionCodeId', 'timesRedeemed',
    ];

    protected $casts = [
        'discountValue'    => 'decimal:2',
        'durationInMonths' => 'integer',
        'maxRedemptions'   => 'integer',
        'timesRedeemed'    => 'integer',
        'expiresAt'        => 'datetime',
        'isActive'         => 'boolean',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class, 'promoCodeId');
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isPast();
    }

    public function isMaxedOut(): bool
    {
        return $this->maxRedemptions !== null
            && $this->timesRedeemed >= $this->maxRedemptions;
    }
}