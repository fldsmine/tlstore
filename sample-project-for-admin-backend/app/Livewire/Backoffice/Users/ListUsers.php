<?php

namespace App\Livewire\Backoffice\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Users — Backoffice')]
class ListUsers extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $type = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetPage();
    }

    public function render(): View
    {
        $users = User::query()
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('username', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term));
            })
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->type === 'agents', fn (Builder $q) => $q->where('is_agent', true))
            ->when($this->type === 'special', fn (Builder $q) => $q->where('is_agent', true)->where('is_special', true))
            ->when($this->type === 'members', fn (Builder $q) => $q->where('is_agent', false))

            ->when($this->type === 'agents', fn (Builder $q) => $q
                ->withCount(['issuedCoupons as sold_coupons_count' => fn (Builder $sub) => $sub->whereNotNull('user_id')])
                ->orderByDesc('agent_balance'), fn (Builder $q) => $q->latest())
            ->with('package')
            ->paginate(15)
            ->withQueryString();

        return view('livewire.backoffice.users.list-users', [
            'users' => $users,
            'statuses' => UserStatus::cases(),
        ]);
    }
}
