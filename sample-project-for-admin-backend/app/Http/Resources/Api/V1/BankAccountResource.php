<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BankAccount */
class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank' => $this->when($this->relationLoaded('bank'), fn () => $this->bank ? new BankResource($this->bank) : null),
            'account_number' => $this->account_number,
            'masked_number' => $this->masked_number,
            'account_name' => $this->account_name,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
