<?php

namespace App\Livewire\Backoffice\Coupons;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CouponBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Batch — Backoffice')]
class ShowBatch extends Component
{
    use AuthorizesAdminActions, WithPagination;

    public string $batchId;

    #[Url]
    public string $search = '';

    #[Url]
    public string $scope = 'all';

    public array $selected = [];

    public bool $selectPage = false;

    public function mount(CouponBatch $batch): void
    {
        $this->batchId = $batch->id;
    }

    public function updatedSearch(): void
    {
        $this->reset('selected', 'selectPage');
        $this->resetPage();
    }

    public function setScope(string $scope): void
    {
        $this->scope = $scope;
        $this->reset('selected', 'selectPage');
        $this->resetPage();
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value
            ? $this->filteredCoupons()->whereNull('user_id')->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];
    }

    public function deleteSelected(): void
    {
        $this->authorizeWrite();

        if (empty($this->selected)) {
            return;
        }

        $deleted = DB::transaction(fn () => CouponBatch::query()->findOrFail($this->batchId)
            ->coupons()->whereIn('id', $this->selected)->whereNull('user_id')->delete());

        $this->reset('selected', 'selectPage');
        $this->dispatch('toast', message: $deleted.' token(s) deleted.', type: $deleted ? 'success' : 'warning');
    }

    public function deleteCoupon(string $id): void
    {
        $this->authorizeWrite();

        $deleted = DB::transaction(fn () => CouponBatch::query()->findOrFail($this->batchId)
            ->coupons()->whereKey($id)->whereNull('user_id')->delete());

        $this->dispatch('toast',
            message: $deleted ? 'Token deleted successfully' : 'Cannot delete a redeemed token.',
            type: $deleted ? 'success' : 'warning');
    }

    public function deleteUnused(): void
    {
        $this->authorizeWrite();

        $deleted = DB::transaction(fn () => CouponBatch::query()->findOrFail($this->batchId)
            ->coupons()->whereNull('user_id')->delete());

        $this->dispatch('toast', message: $deleted.' unused token(s) deleted.', type: 'success');
    }

    public function deleteBatch(): mixed
    {
        $this->authorizeWrite();

        $deleted = DB::transaction(function (): bool {
            $batch = CouponBatch::query()->whereKey($this->batchId)->lockForUpdate()->first();
            if (! $batch || $batch->coupons()->whereNotNull('user_id')->exists()) {
                return false;
            }

            $batch->coupons()->delete();
            $batch->delete();

            return true;
        });

        if (! $deleted) {
            $this->dispatch('toast', message: 'Batch has redeemed tokens — delete unused only.', type: 'warning');

            return null;
        }

        return $this->redirect(route('backoffice.batches.index'), navigate: true);
    }

    protected function filteredCoupons(?CouponBatch $batch = null): HasMany
    {

        $batch ??= CouponBatch::query()->findOrFail($this->batchId);

        return $batch->coupons()
            ->when($this->scope === 'active', fn (Builder $q) => $q->whereNull('user_id'))
            ->when($this->scope === 'used', fn (Builder $q) => $q->whereNotNull('user_id'))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('code', 'like', '%'.trim($this->search).'%'));
    }

    public function render(): View
    {
        $batch = CouponBatch::query()
            ->with(['agent', 'package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->withCount([
                'coupons',
                'coupons as used_coupons_count' => fn (Builder $q) => $q->whereNotNull('user_id'),
            ])
            ->findOrFail($this->batchId);

        $coupons = $this->filteredCoupons($batch)
            ->with('redeemer')
            ->latest()
            ->paginate(20);

        return view('livewire.backoffice.coupons.show-batch', [
            'batch' => $batch,
            'coupons' => $coupons,
        ]);
    }
}
