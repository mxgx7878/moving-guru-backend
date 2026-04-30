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
        'interval', 'intervalCount', 'period',
        'stripePriceId', 'stripeProductId', 'features',
        'isFeatured', 'isActive', 'sortOrder',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'features'      => 'array',
        'isFeatured'    => 'boolean',
        'isActive'      => 'boolean',
        'intervalCount' => 'integer',
        'sortOrder'     => 'integer',
    ];

    /**
     * belongsToMany features via plan_features pivot (uses featureId, not key).
     */
    public function planFeatures(): BelongsToMany
    {
        return $this->belongsToMany(
            Feature::class,
            'plan_features',
            'planId',
            'featureId',
        );
    }

    /**
     * Flat array of enabled feature keys — used by frontend gate.
     */
    public function getFeatureKeysAttribute(): array
    {
        if (!$this->relationLoaded('planFeatures')) {
            $this->load('planFeatures');
        }
        return $this->planFeatures->pluck('key')->values()->toArray();
    }

    public function scopeActive($q)
    {
        return $q->where('isActive', true);
    }
}