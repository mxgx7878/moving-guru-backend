<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $table = 'reviews';

    protected $fillable = [
        'reviewer_id',
        'reviewee_id',
        'direction',
        'rating',
        'comment',
        'job_listing_id',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')
                    ->select(['id', 'name', 'email', 'role'])
                    ->with('detail');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id')
                    ->select(['id', 'name', 'email', 'role'])
                    ->with('detail');
    }

    public function jobListing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    public function scopeForReviewee($query, int $userId)
    {
        return $query->where('reviewee_id', $userId);
    }

    public function scopeDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }
}