<?php

namespace App\Livewire\Backoffice\Coupons;

use App\Enums\CouponType;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CouponBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Token Batches — Backoffice')]
class ListBatches extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = 'all';

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {

        $this->detailId = CouponBatch::query()->findOrFail($id)->id;
        $this->showDetail = true;
    }

    public function deleteBatch(string $id): void
    {
        $this->authorizeWrite();
        $this->showDetail = false;

        DB::transaction(function () use ($id): void {
            $batch = CouponBatch::query()->whereKey($id)->lockForUpdate()->first();
            if (! $batch) {
                return;
            }

            if ($batch->coupons()->whereNotNull('user_id')->exists()) {
                $this->dispatch('toast', message: 'Batch has redeemed tokens — use "Delete unused" instead.', type: 'warning');

                return;
            }

            $batch->coupons()->delete();
            $batch->delete();
            $this->dispatch('toast', message: 'Batch deleted successfully', type: 'success');
        });
    }

    public function deleteUnusedInBatch(string $id): void
    {
        $this->authorizeWrite();
        $this->showDetail = false;

        $deleted = DB::transaction(fn () => CouponBatch::query()->findOrFail($id)
            ->coupons()->whereNull('user_id')->delete());

        $this->dispatch('toast', message: $deleted.' unused token(s) deleted.', type: 'success');
    }

    public function render(): View
    {
        $batches = CouponBatch::query()
            ->with(['agent', 'package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->withCount([
                'coupons',
                'coupons as used_coupons_count' => fn (Builder $q) => $q->whereNotNull('user_id'),
            ])
            ->when($this->type !== 'all', fn (Builder $q) => $q->where('type', $this->type))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('reference', 'like', $term)
                    ->orWhereHas('agent', fn (Builder $a) => $a->where('username', 'like', $term)));
            })
            ->latest()
            ->paginate(15);

        return view('livewire.backoffice.coupons.list-batches', [
            'batches' => $batches,
            'types' => CouponType::cases(),
            'detailBatch' => $this->detailId
                ? CouponBatch::query()
                    ->with(['agent', 'package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])
                    ->withCount([
                        'coupons',
                        'coupons as used_coupons_count' => fn (Builder $q) => $q->whereNotNull('user_id'),
                    ])
                    ->find($this->detailId)
                : null,
        ]);
    }
}
