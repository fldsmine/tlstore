<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A cloud-inbox message doubles as the app's notification feed. */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'sender_name' => $this->sender_name,
            'sender_number' => $this->sender_number,
            'avatar_url' => $this->avatar_url,
            'subject' => $this->subject,
            'body' => $this->body,
            'earn_rate_usd' => (float) $this->earn_rate,
            'is_sponsored' => $this->is_sponsored,
            'is_claimable' => $this->is_claimable,
            'claim_host' => $this->claim_host,
            'read_at' => $this->read_at?->toIso8601String(),
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
