<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowPostPayment extends Model
{
    protected $fillable = [
        'user_id', 'contact_email', 'stripe_customer_id', 'public_token_hash',
        'grow_post_id', 'purpose', 'pricing_tier',
        'grow_promo_code_id', 'promo_code',
        'duration_days', 'amount', 'original_amount', 'discount_amount', 'currency',
        'stripe_payment_intent_id', 'status', 'captured_at',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
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

    public function growPromoCode(): BelongsTo
    {
        return $this->belongsTo(GrowPromoCode::class, 'grow_promo_code_id');
    }
}
