<?php

namespace App\Models;

use App\Enums\ChatMessageSender;
use App\Enums\ChatMessageType;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sender' => ChatMessageSender::class,
            'type' => ChatMessageType::class,
            'meta' => 'array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /** URL for an image/file attachment (kept private on S3 via a short-lived signed URL). */
    public function getAttachmentUrlAttribute(): ?string
    {
        $path = $this->meta['path'] ?? null;
        if (blank($path)) {
            return null;
        }
        $disk = Storage::disk(config('filesystems.default'));

        try {
            return $disk->temporaryUrl($path, now()->addMinutes(30)); // private on S3
        } catch (\Throwable) {
            return $disk->url($path); // local disks don't sign
        }
    }

    public function getAttachmentIsImageAttribute(): bool
    {
        return $this->type === ChatMessageType::IMAGE;
    }

    /** Original filename for a file attachment (falls back to the stored basename). */
    public function getAttachmentNameAttribute(): string
    {
        return $this->meta['name'] ?? basename((string) ($this->meta['path'] ?? 'attachment'));
    }
}
