<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\VtuOrder */
class VtuOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'service' => $this->service,
            'service_id' => $this->service_id,
            'provider' => $this->provider,
            'status' => $this->status?->value,
            'recipient' => $this->billers_code ?? $this->phone,
            'phone' => $this->phone,
            'variation_code' => $this->variation_code,
            'amount_ngn' => (float) $this->amount_ngn,
            'amount_usd' => (float) $this->amount_usd,
            // The delivered token (electricity / education pins) — only shown
            // once the order actually succeeded.
            'token' => $this->when($this->status?->value === 'delivered' && filled($this->token), $this->token),
            'plan_label' => $this->metaSnapshot()['plan_label'] ?? null,
            'customer_name' => $this->metaSnapshot()['customer_name'] ?? null,
            'refunded' => (bool) $this->refunded,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function metaSnapshot(): array
    {
        return is_array($this->meta) ? $this->meta : [];
    }
}
