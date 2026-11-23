<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChatMessage */
class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender?->value,
            'type' => $this->type?->value,
            'body' => $this->body,
            'attachment_url' => $this->attachment_url,
            'attachment_is_image' => $this->attachment_is_image,
            'attachment_name' => is_array($this->meta) ? ($this->meta['name'] ?? null) : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
