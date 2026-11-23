<?php

namespace App\Livewire\Backoffice\Notifications;

use App\Enums\NotificationDeliveryStatus;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Notification delivery')]
class NotificationsIndex extends Component
{
    use AuthorizesAdminActions;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $channel = 'all';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedChannel(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $search = trim($this->search);

        $counts = NotificationDelivery::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $channels = NotificationDelivery::query()
            ->selectRaw('channel, count(*) as aggregate')
            ->groupBy('channel')
            ->pluck('aggregate', 'channel');

        $query = NotificationDelivery::query()
            ->with(['notification:id,user_id,type,title', 'notification.user:id,username'])
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->channel !== 'all', fn (Builder $q) => $q->where('channel', $this->channel))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $rows) => $rows
                ->where('provider_message_id', 'like', "%{$search}%")
                ->orWhereHas('notification', fn ($n) => $n->where('title', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%"))
                ->orWhereHas('notification.user', fn ($u) => $u->where('username', 'like', "%{$search}%"))))
            ->latest('id');

        return view('livewire.backoffice.notifications.notifications-index', [
            'deliveries' => $query->paginate(20)->withQueryString(),
            'counts' => $counts,
            'channels' => $channels,
        ]);
    }
}
