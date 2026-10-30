<?php

namespace App\Livewire\Backoffice\Products;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\ProductOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Backoffice → Product orders: every guest eSIM sale from the product pages.
 *
 * Fulfilment here is a human step (the buyer gets an email with the product), so the page is built
 * around one question — what has been paid for and not yet sent? — with the money trail next to it:
 * who bought, what they bought, what they were charged, and whether the payment reference was
 * settled exactly once.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Product Orders — Backoffice')]
class Orders extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $queueFilter = '';

    #[Url]
    public int $perPage = 25;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedQueueFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /** Clamp the admin-chosen page size to a safe allow-list (?perPage= tampering). */
    protected function pageSize(): int
    {
        return in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;
    }

    protected function baseQuery(): Builder
    {
        return ProductOrder::query()
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(fn (Builder $sub) => $sub
                    ->where('reference', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('full_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('gateway_reference', 'like', $term));
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->queueFilter === 'to_send', fn (Builder $query) => $query->awaitingFulfilment())
            ->when($this->queueFilter === 'sent', fn (Builder $query) => $query->paid()->whereNotNull('fulfilled_at'));
    }

    public function render(): View
    {
        return view('livewire.backoffice.products.orders', [
            'orders' => $this->baseQuery()->latest()->paginate($this->pageSize()),
            'paidToday' => ProductOrder::query()->paid()->where('paid_at', '>=', now()->startOfDay())->count(),
            'awaitingFulfilment' => ProductOrder::query()->awaitingFulfilment()->count(),
            'revenueToday' => (float) ProductOrder::query()->paid()->where('paid_at', '>=', now()->startOfDay())->sum('amount_ngn'),
            'revenueAllTime' => (float) ProductOrder::query()->paid()->sum('amount_ngn'),
        ]);
    }
}
