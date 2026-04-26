<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedInstructor extends Model
{
    protected $table = 'saved_instructors';

    protected $fillable = [
        'studio_id',
        'instructor_id',
    ];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'studio_id');
    }

    public function instructor(): BelongsTo
    {
        // Eager-load detail so the Saved Instructors page renders
        // full cards without a second query.
        return $this->belongsTo(User::class, 'instructor_id')
                    ->select(['id', 'name', 'email', 'role'])
                    ->with('detail');
    }
}