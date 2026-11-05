<?php

namespace App\Models;

use App\Enums\WithdrawalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WithdrawalRequest extends Model
{
    use TrustLensMorphMap;

    protected $table = 'withdrawal_requests';
    protected $fillable = [
        'user_id', 'wallet_id', 'withdrawal_option_id', 'amount_minor', 'fee_minor', 'currency',
        'payout_details', 'status', 'idempotency_key', 'provider_reference', 'processed_by',
        'failure_reason', 'requested_at', 'processed_at', 'processed_by_admin_id',
    ];
    protected $casts = [
        'amount_minor' => 'integer',
        'fee_minor' => 'integer',
        'payout_details' => 'array',
        'status' => WithdrawalRequestStatus::class,
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(WithdrawalOption::class, 'withdrawal_option_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by_admin_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'source_id')->where('source_type', 'withdrawal_request');
    }
}
