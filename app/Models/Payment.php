<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'userId', 'subscriptionId',
        'stripeInvoiceId', 'stripePaymentIntentId',
        'amount', 'currency', 'status', 'paidAt',
        'description', 'hostedInvoiceUrl', 'invoicePdfUrl',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paidAt' => 'datetime',
    ];

    public function user(): BelongsTo         { return $this->belongsTo(User::class, 'userId'); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class, 'subscriptionId'); }
}