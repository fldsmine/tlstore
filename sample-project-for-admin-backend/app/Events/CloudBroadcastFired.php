<?php

namespace App\Events;

use App\Models\CloudBroadcast;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CloudBroadcastFired implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CloudBroadcast $broadcast) {}

    public function broadcastOn(): array
    {
        return [new Channel('cloud-broadcasts')];
    }

    public function broadcastWith(): array
    {
        return [
            'broadcastId' => $this->broadcast->id,
            'caller' => $this->broadcast->caller_name,
            'name' => $this->broadcast->number_name,
        ];
    }
}
