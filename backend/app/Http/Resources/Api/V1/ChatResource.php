<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A chat conversation. `other` is resolved by the controller for the requesting participant. */
class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $other = $this->when(isset($this->resource->api_other), fn () => $this->resource->api_other ? [
            'id' => $this->resource->api_other->id,
            'username' => $this->resource->api_other->username,
            'full_name' => $this->resource->api_other->full_name,
            'avatar_url' => $this->resource->api_other->avatar,
            'is_agent' => (bool) $this->resource->api_other->is_agent,
        ] : null);

        return [
            'id' => $this->id,
            'other' => $other,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread_count' => $this->when(isset($this->resource->api_unread_count), fn () => (int) $this->resource->api_unread_count),
        ];
    }
}
