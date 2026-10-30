<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MinuteTopUp */
class MinuteTopUpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_name' => $this->when($this->relationLoaded('plan'), fn () => $this->plan?->name),
            'daily_call_minutes' => (float) $this->daily_call_minutes,
            'share_minutes' => (float) $this->share_minutes,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'share_expires_at' => $this->share_expires_at?->toIso8601String(),
            'active' => $this->expires_at !== null && $this->expires_at->isFuture(),
            'share_active' => (float) $this->share_minutes > 0
                && $this->share_expires_at !== null
                && $this->share_expires_at->isFuture()
                && $this->shareSubscription?->status === 'active'
                && $this->shareSubscription?->expires_at?->isFuture(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
