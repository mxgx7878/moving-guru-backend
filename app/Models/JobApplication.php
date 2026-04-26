<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    protected $table = 'job_applications';

    protected $fillable = [
        'job_listing_id',
        'instructor_id',
        'message',
        'status',      // pending | viewed | accepted | rejected | withdrawn
        'viewed_at',
        'rejected_at',
    ];

    protected $casts = [
        'viewed_at'   => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function jobListing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')
                    ->select(['id', 'name', 'email', 'role'])
                    ->with('detail');
    }
}