<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Ad */
class AdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'advertiser' => $this->advertiser,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'target_url' => $this->target_url,
            'reward_usd' => (float) $this->reward,
            'shares' => (int) $this->shares,
            // Populated by the controller from AdService (version-aware).
            'earned_current' => $this->when(
                isset($this->resource->earned_current),
                fn () => (bool) $this->resource->earned_current,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
