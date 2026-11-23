<?php

namespace App\Livewire\Backoffice\Users;

use App\Enums\UserStatus;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Users')]
class ListUsers extends Component
{
    use AuthorizesAdminActions;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url(as: 'order')]
    public string $sort = 'latest';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function setSort(string $sort): void
    {
        $this->sort = in_array($sort, ['latest', 'oldest', 'name'], true) ? $sort : 'latest';
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $search = trim($this->search);

        $query = User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(fn (Builder $q) => $q
                    ->where('username', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('full_name', 'like', $term));
            })
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status));

        $query = match ($this->sort) {
            'oldest' => $query->oldest('id'),
            'name' => $query->orderBy('full_name')->orderBy('username'),
            default => $query->latest('id'),
        };

        return view('livewire.backoffice.users.list-users', [
            'users' => $query->paginate(15)->withQueryString(),
            'statuses' => UserStatus::cases(),
        ]);
    }
}
