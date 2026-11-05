<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';
    protected $fillable = [
        'payment_intent_id', 'provider_transaction_id', 'transaction_type', 'amount_minor',
        'currency', 'status', 'provider_response', 'occurred_at',
    ];
    protected $casts = [
        'amount_minor' => 'integer',
        'provider_response' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }
}
