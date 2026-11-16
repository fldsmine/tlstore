<?php

namespace App\Models;

use App\Enums\VtuOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VtuOrder extends Model
{
    protected $fillable = [
        'user_id', 'wallet_id', 'debit_ledger_entry_id', 'service', 'service_id', 'provider',
        'variation_code', 'customer_id', 'customer_name', 'phone', 'amount_ngn_minor',
        'charged_amount_minor', 'currency', 'exchange_rate', 'fee_minor', 'status',
        'request_id', 'idempotency_key', 'payload_hash', 'external_id', 'token', 'refunded',
        'meta', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'encrypted',
            'customer_name' => 'encrypted',
            'token' => 'encrypted',
            'amount_ngn_minor' => 'integer',
            'charged_amount_minor' => 'integer',
            'fee_minor' => 'integer',
            'exchange_rate' => 'decimal:12',
            'status' => VtuOrderStatus::class,
            'refunded' => 'boolean',
            'meta' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function debitLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'debit_ledger_entry_id');
    }
}
