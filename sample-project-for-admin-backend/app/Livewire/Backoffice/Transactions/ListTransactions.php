<?php

namespace App\Livewire\Backoffice\Transactions;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Transactions — Backoffice')]
class ListTransactions extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = 'all';

    #[Url]
    public string $direction = 'all';

    #[Url]
    public string $status = 'all';

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetPage();
    }

    public function setDirection(string $direction): void
    {
        $this->direction = $direction;
        $this->resetPage();
    }

    public function render(): View
    {
        $transactions = Transaction::query()
            ->with('user')
            ->when($this->type !== 'all', fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->direction !== 'all', fn (Builder $q) => $q->where('direction', $this->direction))
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('reference', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('username', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->latest()
            ->paginate(25);

        return view('livewire.backoffice.transactions.list-transactions', [
            'transactions' => $transactions,
            'types' => TransactionType::cases(),
            'detailTx' => $this->detailId ? Transaction::query()->with('user')->find($this->detailId) : null,
        ]);
    }
}
