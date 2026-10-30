<?php

namespace App\Livewire\Backoffice\Support;

use App\Models\SupportTicket;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Support Tickets — Backoffice')]
class ListSupportTickets extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, [...SupportTicket::STATUSES, 'all'], true) ? $status : 'open';
        $this->resetPage();
    }

    public function render(): View
    {
        $query = SupportTicket::query()
            ->with(['user:id,username,first_name,last_name,email', 'latestMessage'])
            ->when($this->status !== 'all', fn (Builder $builder) => $builder->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $builder): void {
                $search = trim($this->search);
                $term = '%'.$search.'%';
                $builder->where(function (Builder $match) use ($term, $search): void {
                    $match->where('subject', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('contact_email', 'like', $term)
                        ->orWhere('id', 'like', $term)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('username', 'like', $term)
                            ->orWhere('email', 'like', $term));

                    if (str_starts_with(strtoupper($search), 'SUP-')) {
                        $match->orWhereRaw(
                            "upper(substr(replace(id, '-', ''), 1, 8)) = ?",
                            [strtoupper(substr($search, 4, 8))],
                        );
                    }
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        return view('livewire.backoffice.support.list-support-tickets', [
            'tickets' => $query->paginate(20),
            'counts' => SupportTicket::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'filters' => [
                'open' => 'Open',
                'waiting_on_user' => 'Waiting for user',
                'closed' => 'Closed',
                'all' => 'All tickets',
            ],
        ]);
    }
}
