<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Withdrawal */
class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'wallet' => $this->wallet,
            'wallet_label' => $this->wallet_label,
            'status' => $this->status?->value,
            'amount_usd' => (float) $this->amount,
            'fee_usd' => (float) $this->fee,
            'amount_paid_usd' => (float) $this->amount_paid,
            'local_currency' => $this->local_currency,
            'local_amount' => $this->local_amount !== null ? (float) $this->local_amount : null,
            'local_amount_paid' => $this->local_amount_paid !== null ? (float) $this->local_amount_paid : null,
            'bank_name' => $this->metaSnapshot()['bank_name'] ?? null,
            'account_number' => $this->metaSnapshot()['account_number'] ?? null,
            'account_name' => $this->metaSnapshot()['account_name'] ?? null,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function metaSnapshot(): array
    {
        return is_array($this->meta) ? $this->meta : [];
    }
}
