<?php

namespace App\Models;

use App\Models\Concerns\RecordsPayoutStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An outbound payout (disbursement) made from the Korapay merchant wallet by a backoffice admin.
 * One row per attempt: the reference is unique, so the same reference can never be sent twice, and
 * the row is the audit trail the "Verify Payout" page reconciles against the Payout Verification API.
 */
class KorapayPayout extends Model
{
    use HasFactory, HasUuids, RecordsPayoutStatus;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'request' => 'array',
            'response' => 'array',
            'initiated_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    protected static function referencePrefix(): string
    {
        return 'KPO-';
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
