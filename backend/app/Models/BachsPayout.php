<?php

namespace App\Models;

use App\Models\Concerns\RecordsPayoutStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An outbound payout made from the Bachs balance by a backoffice admin. One row per attempt: the
 * reference is unique so the same reference can never be sent twice, and the row is the audit trail
 * the "Verify payout" page reconciles against the Payouts API. Standalone by design — never linked to
 * registered users or the withdrawal system.
 *
 * Bachs pays a payout DESTINATION (`pd_…`, the registered bank account) and returns its own payout id
 * (`pay_…`) — the id lives in `gateway_reference`, and the destination is kept alongside it so the
 * account this money went to is readable from the audit row alone.
 */
class BachsPayout extends Model
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
        return 'BCH-';
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
