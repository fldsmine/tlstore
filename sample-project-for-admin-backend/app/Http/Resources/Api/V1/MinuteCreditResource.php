<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A Pay Later credit (minutes bought on credit). */
class MinuteCreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_name' => $this->plan_name,
            'amount_usd' => (float) $this->amount,
            'minutes' => (float) $this->minutes,
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status?->value,
            'repaid_at' => $this->repaid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
