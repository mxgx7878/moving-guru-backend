<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $table = 'features';

    protected $fillable = ['key', 'label', 'description', 'role', 'sortOrder'];

    protected $casts = [
        'sortOrder' => 'integer',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(
            Plan::class,
            'plan_features',
            'featureId',
            'planId',
        );
    }
}