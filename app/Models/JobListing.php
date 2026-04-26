<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobListing extends Model
{
    protected $table = 'job_listings';

    protected $fillable = [
        'studio_id',
        'title',
        'type',
        'role_type',
        'description',
        'disciplines',
        'location',
        'start_date',
        'duration',
        'compensation',
        'requirements',
        'qualification_level',
        'is_active',
         'vacancies',
        'positions_filled',
    ];

    protected $casts = [
        'disciplines' => 'array',
        'is_active'   => 'boolean',
        'start_date'  => 'date:Y-m-d',
        'vacancies' => 'integer',
        'positions_filled' => 'integer',
    ];

    // ── Relationships ───────────────────────────────────────────

    public function studio(): BelongsTo
    {
        // Eager-loads the studio's detail (studioName, location, avatar) so
        // the instructor-facing list can render a studio card without an
        // extra round-trip.
        return $this->belongsTo(User::class, 'studio_id')
                    ->select(['id', 'name', 'email', 'role'])
                    ->with('detail');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_listing_id');
    }

    // ── Scopes ──────────────────────────────────────────────────

    /** Only listings that are switched on by the studio. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Filter by listing type (hire | swap | energy_exchange). */
    public function scopeOfType($query, ?string $type)
    {
        return $type ? $query->where('type', $type) : $query;
    }

    /** Partial location match — used by the public search. */
    public function scopeInLocation($query, ?string $location)
    {
        return $location
            ? $query->where('location', 'like', "%{$location}%")
            : $query;
    }

    /** Discipline search via JSON contains (MySQL 5.7+ / MariaDB 10.2+). */
    public function scopeHasDiscipline($query, ?string $discipline)
    {
        return $discipline
            ? $query->whereJsonContains('disciplines', $discipline)
            : $query;
    }

     public function isFull(): bool
    {
        return $this->positions_filled >= $this->vacancies;
    }
 
    /** Positions still open for hire. Never negative. */
    public function positionsOpen(): int
    {
        return max(0, $this->vacancies - $this->positions_filled);
    }
}