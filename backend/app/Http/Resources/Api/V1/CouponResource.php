<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A vendor-minted token. */
class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type?->value,
            'type_name' => $this->type?->getName(),
            'region' => $this->region,
            'label' => $this->displayName(),
            'price_usd' => (float) $this->price,
            'is_valid' => $this->is_valid,
            'used_at' => $this->used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
