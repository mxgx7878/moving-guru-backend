<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrowPostTier extends Model
{
    protected $fillable = [
        'name', 'description', 'price', 'currency',
        'duration_days', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
