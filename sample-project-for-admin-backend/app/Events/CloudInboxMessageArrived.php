<?php

namespace App\Events;

use App\Models\CloudMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CloudInboxMessageArrived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CloudMessage $message) {}

    public function broadcastOn(): array
    {
        return [new Channel('cloud-inbox-messages')];
    }

    public function broadcastWith(): array
    {
        return [
            'messageId' => $this->message->id,
            'sender' => $this->message->sender_name,
        ];
    }
}
