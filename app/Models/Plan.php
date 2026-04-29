<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'description', 'price', 'currency',
        'interval', 'intervalCount', 'period',
        'stripePriceId', 'features', 'isFeatured', 'isActive', 'sortOrder',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'features'      => 'array',
        'isFeatured'    => 'boolean',
        'isActive'      => 'boolean',
        'intervalCount' => 'integer',
        'sortOrder'     => 'integer',
    ];

    public function scopeActive($q) { return $q->where('isActive', true); }
}