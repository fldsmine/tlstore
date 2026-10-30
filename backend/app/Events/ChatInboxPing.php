<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Contentless ping on a user's personal channel — their global ChatNotifier re-fetches
 * (authorized) activity server-side and toasts it, wherever they are on the site.
 */
class ChatInboxPing implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $userId) {}

    public function broadcastOn(): array
    {
        return [new Channel('chat-inbox.'.$this->userId)];
    }

    public function broadcastWith(): array
    {
        return ['userId' => $this->userId];
    }
}
