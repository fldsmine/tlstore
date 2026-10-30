<?php

namespace App\Livewire\Backoffice\Coupons;

use App\Enums\CouponType;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Coupon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Coupons — Backoffice')]
class ListCoupons extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = 'all';

    #[Url]
    public string $status = 'all';

    public array $selected = [];

    public bool $selectPage = false;

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->reset('selected', 'selectPage');
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
        $this->reset('selected', 'selectPage');
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
        $this->reset('selected', 'selectPage');
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value ? $this->baseQuery()->paginate(20)->pluck('id')->all() : [];
    }

    protected function baseQuery(): Builder
    {
        return Coupon::query()
            ->with(['agent', 'redeemer', 'batch', 'package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->when($this->type !== 'all', fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->status === 'used', fn (Builder $q) => $q->whereNotNull('user_id'))
            ->when($this->status === 'unused', fn (Builder $q) => $q->whereNull('user_id'))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('code', 'like', $term)
                    ->orWhereHas('agent', fn (Builder $a) => $a->where('username', 'like', $term)));
            })
            ->latest();
    }

    public function deleteCoupon(string $id): void
    {
        $this->authorizeWrite();
        $this->showDetail = false;

        $deleted = DB::transaction(fn () => Coupon::query()->whereKey($id)->whereNull('user_id')->delete());

        $this->dispatch('toast',
            message: $deleted ? 'Coupon deleted successfully' : 'Cannot delete a redeemed coupon.',
            type: $deleted ? 'success' : 'warning');
    }

    public function deleteSelected(): void
    {
        $this->authorizeWrite();

        $deleted = DB::transaction(fn () => Coupon::query()
            ->whereIn('id', $this->selected)
            ->whereNull('user_id')
            ->delete());

        $this->reset('selected', 'selectPage');
        $this->dispatch('toast', message: $deleted.' coupon(s) deleted (redeemed ones kept).', type: 'success');
    }

    public function deleteAllUnused(): void
    {
        $this->authorizeWrite();

        $deleted = DB::transaction(fn () => $this->baseQuery()->whereNull('user_id')->delete());

        $this->reset('selected', 'selectPage');
        $this->dispatch('toast', message: $deleted.' unused coupon(s) deleted.', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.coupons.list-coupons', [
            'coupons' => $this->baseQuery()->paginate(20),
            'types' => CouponType::cases(),
            'detailCoupon' => $this->detailId
                ? Coupon::query()->with(['agent', 'redeemer', 'batch', 'package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])->find($this->detailId)
                : null,
        ]);
    }
}
