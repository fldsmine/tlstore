<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use record of a settled gateway payment. Inserted inside the settlement transaction
 * BEFORE any entitlement is granted — see App\Services\Payments\PaymentReferenceLedger.
 */
class PaymentReference extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'reference', 'transaction_id', 'user_id', 'provider', 'gateway_reference',
        'type', 'currency', 'amount_ngn', 'settled_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_ngn' => 'decimal:2',
            'settled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
