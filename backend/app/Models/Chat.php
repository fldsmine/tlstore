<?php

namespace App\Models;

use Database\Factories\ChatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A plain conversation (DM) between a customer and a distributor — one persistent thread per pair.
 */
class Chat extends Model
{
    /** @use HasFactory<ChatFactory> */
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'customer_read_at' => 'datetime',
            'agent_read_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    /** The participant on the other side of the conversation from the given user. */
    public function otherParticipant(User $user): ?User
    {
        return $this->agent_id === $user->id ? $this->customer : $this->agent;
    }

    public function scopeForParticipant(Builder $query, string $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('customer_id', $userId)->orWhere('agent_id', $userId);
        });
    }

    /** Mark the thread read up to now for whichever participant this user is. */
    public function markReadFor(User $user): void
    {
        $column = $this->agent_id === $user->id ? 'agent_read_at' : 'customer_read_at';
        $this->forceFill([$column => now()])->saveQuietly();
    }
}
