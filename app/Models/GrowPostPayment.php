<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowPostPayment extends Model
{
    protected $fillable = [
        'user_id', 'contact_email', 'stripe_customer_id', 'public_token_hash',
        'grow_post_id', 'purpose', 'pricing_tier',
        'duration_days', 'amount', 'currency',
        'stripe_payment_intent_id', 'status', 'captured_at',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'amount' => 'decimal:2',
        'captured_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function growPost(): BelongsTo
    {
        return $this->belongsTo(GrowPost::class, 'grow_post_id');
    }
}
