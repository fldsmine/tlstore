<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'type_name' => $this->type?->getName(),
            'direction' => $this->direction,
            'is_credit' => $this->is_credit,
            'amount_usd' => (float) $this->amount,
            'fee_usd' => (float) $this->fee,
            'net_usd' => $this->net_amount,
            'wallet' => $this->wallet,
            'wallet_label' => \App\Models\User::WALLET_LABELS[$this->wallet] ?? null,
            'status' => $this->status,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'description' => $this->description,
            'meta' => is_array($this->meta) ? $this->meta : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
