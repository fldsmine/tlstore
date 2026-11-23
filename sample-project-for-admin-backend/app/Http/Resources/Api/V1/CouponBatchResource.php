<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CouponBatch */
class CouponBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type?->value,
            'type_name' => $this->type?->getName(),
            'region' => $this->region,
            'quantity' => (int) $this->quantity,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
