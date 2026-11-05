<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    use TrustLensMorphMap;

    protected $table = 'ledger_entries';
    protected $fillable = [
        'wallet_id', 'entry_type', 'direction', 'amount_minor', 'status', 'source_type',
        'source_id', 'idempotency_key', 'description', 'available_at', 'posted_at',
    ];
    protected $casts = [
        'direction' => LedgerDirection::class,
        'amount_minor' => 'integer',
        'status' => LedgerEntryStatus::class,
        'available_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }
}
