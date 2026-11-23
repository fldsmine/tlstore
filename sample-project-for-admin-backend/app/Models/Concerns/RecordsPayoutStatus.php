<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared behaviour for an outbound payout audit row (one per provider wallet).
 *
 * Every provider collapses onto the same five states so the backoffice pages, filters and badges
 * behave identically whichever manager is active: processing/pending are in flight, success/failed
 * are final, and `unknown` means the provider never gave a definitive answer — which is NOT a
 * failure (the money may well have moved), so the payout stays open for verification.
 */
trait RecordsPayoutStatus
{
    /** Statuses that are still in flight (never treat as final). */
    public const IN_FLIGHT = ['processing', 'pending', 'unknown'];

    public function isFinal(): bool
    {
        return ! in_array((string) $this->status, self::IN_FLIGHT, true);
    }

    public function statusTone(): string
    {
        return match ((string) $this->status) {
            'success' => 'success',
            'failed' => 'danger',
            'processing' => 'blue',
            'unknown' => 'warning',
            default => 'muted',
        };
    }

    public function statusLabel(): string
    {
        return match ((string) $this->status) {
            'success' => 'Successful',
            'failed' => 'Failed',
            'processing' => 'Processing',
            'unknown' => 'Unverified',
            default => 'Pending',
        };
    }

    public function scopeInFlight(Builder $query): Builder
    {
        return $query->whereIn('status', self::IN_FLIGHT);
    }

    /**
     * A unique payout reference, prefixed per provider so one manager's references can never be
     * mistaken for another's when both are in the ledger.
     */
    public static function generateReference(): string
    {
        do {
            $reference = static::referencePrefix().strtoupper(str()->random(12));
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
