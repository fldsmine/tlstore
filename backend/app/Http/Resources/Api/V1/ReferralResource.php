<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A downline member (direct or indirect) — deliberately minimal. */
class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'avatar_url' => $this->avatar,
            'package_name' => $this->when(
                $this->relationLoaded('package'),
                fn () => $this->package?->name,
            ),
            'level' => $this->when(isset($this->resource->level), fn () => $this->resource->level),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
