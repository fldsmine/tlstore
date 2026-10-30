<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Contentless ping on a chat's channel — both participants' components re-fetch the
 * (authorized) thread server-side. Message content never rides the wire.
 */
class ChatUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $chatId) {}

    public function broadcastOn(): array
    {
        return [new Channel('chat.'.$this->chatId)];
    }

    public function broadcastWith(): array
    {
        return ['chatId' => $this->chatId];
    }
}
