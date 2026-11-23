<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of today's earnings pool. The controller composes plain arrays
 * (record + claim state) and wraps them with this resource.
 */
class CallRecordRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'record_source' => 'daily_pool',
            'usage_verified' => false,
            'type' => $this->resource['type'],
            'amount_usd' => (float) $this->resource['amount'],
            'earned_usd' => (float) $this->resource['earned'],
            'duration_minutes' => (float) $this->resource['durationMinutes'],
            'claimable_minutes' => (float) $this->resource['claimableMinutes'],
            'claimed_minutes' => (float) $this->resource['claimedMinutes'],
            'claimed' => (bool) $this->resource['claimed'],
            'claimed_at' => $this->resource['claimedAt']?->toIso8601String(),
            'occurred_at' => $this->resource['occurredAt']?->toIso8601String(),
            'in_package' => (bool) $this->resource['inPackage'],
            'can_claim' => (bool) $this->resource['canClaim'],
            'blocked_reason' => $this->resource['blockedReason'] ?? null,
            'network' => $this->resource['network'],
        ];
    }
}
