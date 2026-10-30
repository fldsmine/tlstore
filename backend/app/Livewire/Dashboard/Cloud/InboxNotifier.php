<?php

namespace App\Livewire\Dashboard\Cloud;

use App\Models\CloudInboxMessage;
use App\Models\User;
use App\Services\CloudDistributionService;
use App\Services\CloudInboxService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class InboxNotifier extends Component
{
    public string $since = '';

    public int $bonusSeen = 0;

    public function mount(CloudDistributionService $distributions): void
    {
        $this->since = now()->toDateTimeString();
        $this->bonusSeen = $distributions->claimableCount($this->user());
    }

    #[On('echo:cloud-inbox-messages,CloudInboxMessageArrived')]
    public function onArrived(CloudInboxService $inbox, CloudDistributionService $distributions): void
    {
        $this->check($inbox, $distributions);
    }

    #[On('echo:cloud-distributions,CloudDistributionFired')]
    public function onDistribution(CloudInboxService $inbox, CloudDistributionService $distributions): void
    {
        $this->check($inbox, $distributions);
    }

    public function check(CloudInboxService $inbox, CloudDistributionService $distributions): void
    {
        $user = $this->user();

        $new = $user->cloudInbox()
            ->where('created_at', '>', $this->since)
            ->orderBy('created_at')
            ->get(['id', 'type', 'sender_name', 'subject', 'earn_rate', 'claimed_at']);

        if ($new->isNotEmpty()) {
            $this->since = now()->toDateTimeString();
            $this->dispatch('toast', ...($new->count() === 1
                ? $this->toastFor($new->first())
                : ['message' => $new->count().' new messages in your Cloud inbox', 'type' => 'info']));
        }

        $claimable = $distributions->claimableCount($user);
        if ($claimable > $this->bonusSeen) {
            $fresh = $claimable - $this->bonusSeen;
            $this->dispatch('toast',
                message: $fresh === 1 ? 'You have a new bonus to claim in Nextel Cloud.' : $fresh.' new bonuses to claim in Nextel Cloud.',
                type: 'success',
            );
        }
        $this->bonusSeen = $claimable;

        $this->dispatch('cloud-badge', count: $inbox->unreadCount($user) + $claimable);
    }

    protected function toastFor(CloudInboxMessage $m): array
    {
        if ($m->is_sponsored && (float) $m->earn_rate > 0) {
            return [
                'message' => 'New SMS from '.$m->sender_name.' — read to earn '.money((float) $m->earn_rate),
                'type' => 'success',
            ];
        }

        return [
            'message' => $m->sender_name.': '.Str::limit($m->subject, 44),
            'type' => 'info',
        ];
    }

    protected function user(): User
    {
        return auth()->user();
    }

    public function render(): View
    {
        return view('livewire.dashboard.cloud.inbox-notifier');
    }
}
