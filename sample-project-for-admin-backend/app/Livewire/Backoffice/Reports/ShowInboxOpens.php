<?php

namespace App\Livewire\Backoffice\Reports;

use App\Models\CloudInboxMessage;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One user's Cloud Inbox, message by message.
 *
 * The detail behind the Cloud Inbox opens report: what was delivered to them, which
 * messages they opened (with how long they waited), which they claimed, and what the
 * opens earned. Reached from the report, and linked from the user's own page.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Cloud Inbox detail — Backoffice')]
class ShowInboxOpens extends Component
{
    use WithPagination;

    public string $userId;

    /** all | opened | unread | claimed */
    #[Url]
    public string $state = 'all';

    public function mount(User $user): void
    {
        $this->userId = $user->id;
    }

    public function setState(string $state): void
    {
        $this->state = in_array($state, ['all', 'opened', 'unread', 'claimed'], true) ? $state : 'all';
        $this->resetPage();
    }

    protected function subject(): User
    {
        abort_unless(auth('admin')->check(), 403);

        return User::query()->with('package')->findOrFail($this->userId);
    }

    public function render(): View
    {
        $user = $this->subject();

        $messages = CloudInboxMessage::query()
            ->where('user_id', $user->id)
            ->when($this->state === 'opened', fn (Builder $q) => $q->whereNotNull('read_at'))
            ->when($this->state === 'unread', fn (Builder $q) => $q->whereNull('read_at'))
            ->when($this->state === 'claimed', fn (Builder $q) => $q->whereNotNull('claimed_at'))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $inbox = CloudInboxMessage::query()->where('user_id', $user->id);

        return view('livewire.backoffice.reports.show-inbox-opens', [
            'user' => $user,
            'messages' => $messages,
            'received' => (clone $inbox)->count(),
            'opened' => (clone $inbox)->whereNotNull('read_at')->count(),
            'unread' => (clone $inbox)->whereNull('read_at')->count(),
            'claimed' => (clone $inbox)->whereNotNull('claimed_at')->count(),
            'earned' => (float) (clone $inbox)->whereNotNull('claimed_at')->sum('earn_rate'),
            'firstOpened' => (clone $inbox)->whereNotNull('read_at')->min('read_at'),
            'lastOpened' => (clone $inbox)->whereNotNull('read_at')->max('read_at'),
            'sponsored' => (clone $inbox)->where('type', CloudInboxMessage::TYPE_SPONSORED)->count(),
            'system' => (clone $inbox)->where('type', CloudInboxMessage::TYPE_SYSTEM)->count(),
        ]);
    }
}
