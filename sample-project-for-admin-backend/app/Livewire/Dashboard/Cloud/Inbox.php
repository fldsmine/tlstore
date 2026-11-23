<?php

namespace App\Livewire\Dashboard\Cloud;

use App\Models\User;
use App\Services\CloudInboxService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Cloud Inbox')]
class Inbox extends Component
{
    public ?string $threadKey = null;

    public int $limit = self::PAGE;

    private const PAGE = 40;

    public function mount(): void
    {
        abort_unless((bool) config('settings.cloud.enabled', true), 404);
        abort_unless($this->user()->canAccessCloud() && $this->user()->canCloudSms(), 403);
    }

    public function openThread(string $key, CloudInboxService $service): void
    {
        $this->threadKey = $key;
        $this->limit = self::PAGE;

        $service->markThreadRead($this->user(), $key);

        $this->dispatch('cloud-badge', count: cloud_badge_count($this->user()));
    }

    public function back(): void
    {
        $this->reset('threadKey', 'limit');
    }

    public function loadEarlier(): void
    {
        $this->limit += self::PAGE;
    }

    public function claim(string $id, CloudInboxService $service): void
    {
        $message = $this->user()->cloudInbox()->whereKey($id)->first();

        if ($message === null) {
            return;
        }

        $earned = $service->claim($this->user(), $message);

        if ($earned > 0) {
            $toast = ['message' => 'Claimed '.money($earned).' — added to your N-Cloud SMS wallet.', 'type' => 'success'];
        } elseif ($service->smsLimitReached($this->user())) {
            $limit = $this->user()->cloudSmsLimit();
            $toast = ['message' => "Daily SMS limit reached ({$limit} of {$limit}). Add a Cloud storage plan for more SMS access.", 'type' => 'warning'];
        } else {
            $toast = ['message' => 'Nothing left to claim on this message.', 'type' => 'info'];
        }

        $this->dispatch('toast', message: $toast['message'], type: $toast['type']);

        $this->user()->refresh();

        $this->dispatch('cloud-badge', count: cloud_badge_count($this->user()));
    }

    protected function user(): User
    {
        return auth()->user();
    }

    public function render(CloudInboxService $service): View
    {
        $user = $this->user();
        $conversations = $service->conversations($user);

        $conversation = $this->threadKey !== null ? $conversations->firstWhere('key', $this->threadKey) : null;

        if ($conversation === null) {
            $this->threadKey = null;
        }

        return view('livewire.dashboard.cloud.inbox', [
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $conversation ? $this->thread($service, $user) : collect(),
            'earlier' => $conversation ? max(0, $conversation['total'] - $this->limit) : 0,
            'unread' => (int) $conversations->sum('unread'),
            'unclaimed' => (float) $conversations->sum('unclaimed'),
            'balance' => (float) $user->cloud_sms_balance,
            'smsClaimedToday' => $service->smsClaimedToday($user),
            'smsLimit' => $user->cloudSmsLimit(),
            'smsLimitReached' => $service->smsLimitReached($user),
        ]);
    }

    protected function thread(CloudInboxService $service, User $user): Collection
    {
        return $service->threadQuery($user, $this->threadKey)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->limit)
            ->get()
            ->reverse()
            ->values();
    }
}
