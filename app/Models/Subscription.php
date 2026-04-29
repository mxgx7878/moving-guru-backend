<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'userId', 'planId', 'stripeSubscriptionId', 'status',
        'currentPeriodStart', 'currentPeriodEnd',
        'cancelAtPeriodEnd', 'cancelledAt', 'trialEndsAt',
    ];

    protected $casts = [
        'currentPeriodStart' => 'datetime',
        'currentPeriodEnd'   => 'datetime',
        'cancelledAt'        => 'datetime',
        'trialEndsAt'        => 'datetime',
        'cancelAtPeriodEnd'  => 'boolean',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class, 'userId'); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class, 'planId'); }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }
}