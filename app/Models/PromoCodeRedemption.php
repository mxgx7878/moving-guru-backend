<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeRedemption extends Model
{
    protected $table = 'promo_code_redemptions';

    protected $fillable = [
        'promoCodeId', 'userId', 'subscriptionId',
        'stripePromotionCodeId', 'redeemedAt',
    ];

    protected $casts = [
        'redeemedAt' => 'datetime',
    ];

    public function promoCode(): BelongsTo { return $this->belongsTo(PromoCode::class, 'promoCodeId'); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class, 'userId'); }
}