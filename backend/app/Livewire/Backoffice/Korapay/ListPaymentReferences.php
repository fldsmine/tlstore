<?php

namespace App\Livewire\Backoffice\Korapay;

use App\Enums\TransactionType;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\PaymentReference;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Backoffice → Korapay → Payment references: the single-use ledger of settled gateway payments.
 *
 * Every charge that settles writes one row here BEFORE the user is credited, and nothing may credit
 * a reference that already has a row — neither our reference nor the provider's transaction id.
 * This page is the audit trail for that guarantee: search a reference (or a gateway reference) and
 * see exactly which charge it settled, for how much, and when.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Payment References — Backoffice')]
class ListPaymentReferences extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public int $perPage = 25;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
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
        return PaymentReference::query()
            ->with(['user', 'transaction'])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(fn (Builder $sub) => $sub
                    ->where('reference', 'like', $term)
                    ->orWhere('gateway_reference', 'like', $term)
                    ->orWhere('transaction_id', 'like', $term));
            })
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter));
    }

    public function render(): View
    {
        return view('livewire.backoffice.korapay.list-payment-references', [
            'references' => $this->baseQuery()->latest('settled_at')->paginate($this->pageSize()),
            'types' => collect(TransactionType::cases())
                ->mapWithKeys(fn (TransactionType $type): array => [$type->value => $type->getName()])
                ->all(),
            'settledToday' => PaymentReference::query()->where('settled_at', '>=', now()->startOfDay())->count(),
            'settledAllTime' => PaymentReference::query()->count(),
            'flaggedCharges' => Transaction::query()
                ->where('status', 'failed')
                ->whereNotNull('meta->manual_review')
                ->count(),
        ]);
    }
}
