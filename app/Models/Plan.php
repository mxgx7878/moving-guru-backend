<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    protected $table = 'plans';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'description', 'price', 'currency',
        'discountType', 'discountValue', 'discountDuration', 'discountMonths',
        'interval', 'intervalCount', 'period',
        'trialPeriodDays',
        'stripePriceId', 'stripeProductId', 'stripeCouponId', 'features',
        'isFeatured', 'isActive', 'sortOrder',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'discountValue'   => 'decimal:2',
        'features'        => 'array',
        'isFeatured'      => 'boolean',
        'isActive'        => 'boolean',
        'intervalCount'   => 'integer',
        'trialPeriodDays' => 'integer',
        'sortOrder'       => 'integer',
        'discountMonths' => 'integer',
    ];

    // Computed discount info auto-included in every API response.
    protected $appends = ['hasDiscount', 'discountedPrice'];

    public function planFeatures(): BelongsToMany
    {
        return $this->belongsToMany(
            Feature::class,
            'plan_features',
            'planId',
            'featureId',
        );
    }

    public function getFeatureKeysAttribute(): array
    {
        if (!$this->relationLoaded('planFeatures')) {
            $this->load('planFeatures');
        }
        return $this->planFeatures->pluck('key')->values()->toArray();
    }

    public function hasTrial(): bool
    {
        return (int) $this->trialPeriodDays > 0;
    }

    // ── Discount ────────────────────────────────────────────────

    public function getHasDiscountAttribute(): bool
    {
        return in_array($this->discountType, ['percent', 'fixed'], true)
            && (float) $this->discountValue > 0;
    }

    /** Final price after the plan discount — string, 2dp. */
    public function getDiscountedPriceAttribute(): string
    {
        $price = (float) $this->price;

        if (!$this->hasDiscount) {
            return number_format($price, 2, '.', '');
        }

        $final = $this->discountType === 'percent'
            ? $price * (1 - ((float) $this->discountValue / 100))
            : $price - (float) $this->discountValue;

        return number_format(max(0, round($final, 2)), 2, '.', '');
    }

    public function scopeActive($q)
    {
        return $q->where('isActive', true);
    }
}