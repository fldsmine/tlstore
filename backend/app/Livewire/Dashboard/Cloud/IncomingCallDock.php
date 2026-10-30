<?php

namespace App\Livewire\Dashboard\Cloud;

use App\Models\CloudBroadcast;
use App\Models\CloudNumber;
use App\Models\User;
use App\Services\CloudCallService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class IncomingCallDock extends Component
{
    public function mount(CloudCallService $service): void
    {

        if ($call = $service->activeCall($this->user())) {
            $service->endCall($this->user(), $call);
        }
    }

    public function heartbeat(CloudCallService $service): void
    {
        $service->heartbeat($this->user());
    }

    #[On('echo:cloud-broadcasts,CloudBroadcastFired')]
    public function onCloudBroadcast(): void {}

    public function startCall(string $id, CloudCallService $service): void
    {
        try {
            $service->startCall($this->user(), CloudNumber::findOrFail($id));
        } catch (ValidationException $e) {
            $this->dispatch('cloud-call-rejected');
            $this->dispatch('toast', message: $e->validator->errors()->first(), type: 'warning');
        }
    }

    public function completeCall(int $seconds, CloudCallService $service): void
    {
        if (! $call = $service->activeCall($this->user())) {
            return;
        }

        $done = $service->completeCall($this->user(), $call, $seconds);
        $earned = (float) $done->earned;

        $this->dispatch('cloud-updated');

        if ($earned > 0) {
            $this->dispatch('toast', message: 'Reward claimed — earned '.money($earned), type: 'success');

            return;
        }

        $listenedShort = $done->started_at && $done->ended_at
            && $done->started_at->diffInSeconds($done->ended_at) < (int) $done->required_listen_seconds;

        if ($listenedShort) {
            $message = 'You need to listen to the full message to earn.';
        } elseif ($done->cloud_number_id && $service->earnedFromNumberToday($this->user(), $done->cloud_number_id, $done->id)) {
            // Already earned from THIS contact today — earning is once per day per number, so say so plainly.
            $message = 'You have already earned from this contact today.';
        } else {
            // Otherwise the daily/hourly cap was hit mid-call — keep the cap hidden with a neutral line.
            $message = $service->unavailableMessage();
        }

        $this->dispatch('toast', message: $message, type: 'info');
    }

    public function endCall(CloudCallService $service): void
    {
        if ($call = $service->activeCall($this->user())) {
            $service->endCall($this->user(), $call);
            $this->dispatch('cloud-updated');
        }
    }

    public function answerBroadcast(string $broadcastId, CloudCallService $service): void
    {
        $broadcast = CloudBroadcast::query()->find($broadcastId);

        if (! $broadcast) {
            return;
        }

        try {
            $call = $service->answerBroadcast($this->user(), $broadcast);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: $e->validator->errors()->first(), type: 'warning');

            return;
        }

        if (! $call || ! $call->number) {
            $this->dispatch('toast', message: 'That call is no longer available.', type: 'info');

            return;
        }

        $this->dispatch('cloud-incoming-answered', call: $service->payload($call->number));
    }

    public function declineBroadcast(string $broadcastId, CloudCallService $service): void
    {
        if ($broadcast = CloudBroadcast::query()->find($broadcastId)) {
            $service->declineBroadcast($this->user(), $broadcast);
        }
    }

    protected function user(): User
    {
        return auth()->user();
    }

    public function render(CloudCallService $service): View
    {
        $user = $this->user();

        $pending = $service->activeCall($user) ? null : $service->pendingBroadcastFor($user);
        $incoming = $pending ? [
            'broadcastId' => $pending->id,
            'caller' => $pending->caller_name,
            'name' => $pending->number_name,
            'line' => $pending->number?->line_number,
            'brand' => $pending->number?->brand,
        ] : null;

        return view('livewire.dashboard.cloud.incoming-call-dock', [
            'incoming' => $incoming,
        ]);
    }
}
