<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Transient "someone is typing" ping on a chat's public channel. Purely presentational — the
 * recipient's composer shows a short-lived indicator; nothing is persisted.
 */
class ChatTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $chatId, public string $role) {}

    public function broadcastOn(): array
    {
        return [new Channel('chat.'.$this->chatId)];
    }

    public function broadcastAs(): string
    {
        return 'ChatTyping';
    }

    public function broadcastWith(): array
    {
        return ['role' => $this->role];
    }
}
