<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowPost extends Model
{
    protected $table = 'grow_posts';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'subtitle',
        'description',
        'location',
        'date_from',
        'date_to',
        'price',
        'spots',
        'spots_left',
        'disciplines',
        'tags',
        'images',
        'external_url',
        'status',
        'expires_at',
        'is_featured',
        'boost_until',
        'color',
    ];

    protected $casts = [
        'disciplines'  => 'array',
        'tags'         => 'array',
        'images'       => 'array',
        'is_featured'  => 'boolean',
        'date_from'    => 'date:Y-m-d',
        'date_to'      => 'date:Y-m-d',
        'boost_until'  => 'date:Y-m-d',
        'expires_at'   => 'datetime',
        'spots'        => 'integer',
        'spots_left'   => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
                    ->select(['id', 'name', 'email', 'role']);
    }

    // ── Scopes ───────────────────────────────────────────────────

    /** Only approved posts visible to public */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /** Only posts that haven't expired */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /** Featured / boosted first */
    public function scopeFeaturedFirst($query)
    {
        return $query->orderByDesc('is_featured')->orderByDesc('created_at');
    }
}
