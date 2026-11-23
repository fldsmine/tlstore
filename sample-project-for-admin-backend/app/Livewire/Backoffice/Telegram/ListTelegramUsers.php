<?php

namespace App\Livewire\Backoffice\Telegram;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\TelegramIdentity;
use App\Models\TelegramMessage;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Mini App users — Backoffice')]
class ListTelegramUsers extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filter = 'all';

    public bool $showBroadcast = false;

    public string $broadcastBody = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function openBroadcast(): void
    {
        $this->authorizeWrite();
        $this->reset('broadcastBody');
        $this->resetValidation();
        $this->showBroadcast = true;
    }

    public function sendBroadcast(TelegramMessenger $messenger): void
    {
        $this->authorizeWrite();

        $this->validate([
            'broadcastBody' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        $admin = auth('admin')->user();
        abort_unless($admin !== null, 403);

        $result = $messenger->broadcast(trim($this->broadcastBody), $admin);

        $this->showBroadcast = false;
        $this->reset('broadcastBody');

        if ($result['total'] === 0) {
            $this->dispatch('toast', message: 'No Mini App users to message yet.', type: 'info');

            return;
        }

        $this->dispatch('toast', message: "Sent to {$result['sent']} of {$result['total']} Mini App users.", type: $result['failed'] > 0 ? 'warning' : 'success');
    }

    public function render(TelegramBotClient $bot): View
    {
        $identities = TelegramIdentity::query()
            ->with(['user.package', 'latestMessage'])
            ->withCount([
                'messages as unread_count' => fn (Builder $q) => $q
                    ->where('direction', TelegramMessage::DIRECTION_INBOUND)
                    ->whereNull('read_at'),
            ])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $needle = trim($this->search);
                $term = '%'.$needle.'%';
                $query->where(function (Builder $q) use ($term, $needle): void {
                    $q->where('username', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('username', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));

                    if (ctype_digit($needle)) {
                        $q->orWhere('telegram_user_id', (int) $needle);
                    }
                });
            })
            ->when($this->filter === 'unread', fn (Builder $q) => $q->whereHas(
                'messages',
                fn (Builder $m) => $m->where('direction', TelegramMessage::DIRECTION_INBOUND)->whereNull('read_at'),
            ))
            ->orderByDesc('unread_count')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $unreadConversations = TelegramIdentity::query()
            ->whereHas(
                'messages',
                fn (Builder $q) => $q->where('direction', TelegramMessage::DIRECTION_INBOUND)->whereNull('read_at'),
            )
            ->count();

        return view('livewire.backoffice.telegram.list-telegram-users', [
            'identities' => $identities,
            'totalUsers' => TelegramIdentity::query()->count(),
            'linkedThisWeek' => TelegramIdentity::query()->where('created_at', '>=', now()->subWeek())->count(),
            'unreadConversations' => $unreadConversations,
            'botConfigured' => $bot->isConfigured(),
        ]);
    }
}
