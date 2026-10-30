<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RewardTarget */
class RewardTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'target_amount_usd' => (float) $this->target_amount,
            'reward_amount_usd' => (float) $this->reward_amount,
            'reward_percent' => (float) $this->reward_percent,
            'status' => $this->status?->value,
            // Progress is attached by the controller (needs user transactions).
            'progress_usd' => $this->when(
                isset($this->resource->progress),
                fn () => (float) $this->resource->progress,
            ),
            'achieved_at' => $this->achieved_at?->toIso8601String(),
            'claimed_at' => $this->claimed_at?->toIso8601String(),
        ];
    }
}
