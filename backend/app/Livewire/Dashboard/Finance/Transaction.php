<?php

namespace App\Livewire\Dashboard\Finance;

use App\Enums\TransactionType;
use App\Livewire\Concerns\InteractsWithTransactionDetails;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Transaction')]
class Transaction extends Component
{
    use InteractsWithTransactionDetails, WithPagination;

    public string $filter = 'all';

    public string $search = '';

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();

        $query = $user->transactions()->latest('created_at');

        match ($this->filter) {
            'earned' => $query->where('direction', 'credit'),
            'pending' => $query->where('status', 'pending'),
            'withdrawn' => $query->where('type', TransactionType::WITHDRAWAL->value),
            default => null,
        };

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $term)->orWhere('description', 'like', $term));
        }

        return view('livewire.dashboard.finance.transaction', [
            'user' => $user,
            'transactions' => $query->paginate(12),
            'selectedTx' => $this->selectedTransaction(),
            'totalEarned' => (float) $user->lifetime_earnings,
            'pending' => (float) $user->transactions()->where('status', 'pending')->sum('amount'),
            'withdrawn' => (float) $user->transactions()
                ->where('type', TransactionType::WITHDRAWAL->value)
                ->where('status', 'completed')
                ->sum('amount'),
        ]);
    }
}
