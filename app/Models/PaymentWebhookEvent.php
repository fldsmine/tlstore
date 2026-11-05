<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $table = 'payment_webhook_events';
    protected $fillable = [
        'provider', 'provider_event_id', 'event_type', 'payload', 'signature_valid',
        'processing_status', 'processing_error', 'received_at', 'processed_at',
    ];
    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
