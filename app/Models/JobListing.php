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
        'types',         
        'role_type',
        'description',
        'disciplines',
        'location',
        'start_date',
        'duration',
        'compensation',
        'requirements',
        'cover_image',
        'qualification_level',
        'is_active',
         'vacancies',
        'positions_filled',
    ];

    protected $casts = [
        'disciplines' => 'array',
        'types'            => 'array',
        'is_active'   => 'boolean',
        'start_date'  => 'date:Y-m-d',
        'vacancies' => 'integer',
        'positions_filled' => 'integer',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'studio_id')
                    ->select(['id', 'name', 'email', 'role'])
                    ->with('detail');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_listing_id');
    }

    /** Only listings that are switched on by the studio. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Filter by listing type (hire | swap | energy_exchange). */
    public function scopeOfType($query, ?string $type)
    {
        if (!$type) return $query;
        return $query->where(function ($q) use ($type) {
            $q->whereJsonContains('types', $type)
            ->orWhere('type', $type);
        });
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