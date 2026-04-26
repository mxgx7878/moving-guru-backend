<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = [
        'type', 'title', 'body', 'audience', 'status',
        'cover_url', 'link_url', 'link_label',
        'event_date', 'event_location',
        'is_pinned', 'published_at', 'created_by',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'event_date'   => 'datetime',
        'published_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────────────
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ──────────────────────────────────────────────

    /** Only posts that are live on the platform. */
    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    /** Posts visible to a given user role — 'all' OR their specific audience. */
    public function scopeForRole($q, string $role)
    {
        // Map role → audience bucket. Admins see everything, so callers
        // should skip this scope entirely for admins.
        $audience = match ($role) {
            'instructor' => 'instructors',
            'studio'     => 'studios',
            default      => null,
        };

        if (!$audience) return $q;

        return $q->whereIn('audience', ['all', $audience]);
    }
}