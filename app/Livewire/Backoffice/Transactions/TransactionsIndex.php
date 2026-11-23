<?php

namespace App\Livewire\Backoffice\Transactions;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Transactions')]
class TransactionsIndex extends Component
{
    use AuthorizesAdminActions;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $direction = 'all';

    #[Url]
    public string $status = 'all';

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDirection(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $search = trim($this->search);

        $query = Transaction::query()
            ->with('user:id,username,full_name')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $rows) => $rows
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->when($this->direction !== 'all', fn (Builder $q) => $q->where('direction', $this->direction))
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from) === 1, fn (Builder $q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to) === 1, fn (Builder $q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('id');

        return view('livewire.backoffice.transactions.transactions-index', [
            'transactions' => $query->paginate(20)->withQueryString(),
        ]);
    }
}
