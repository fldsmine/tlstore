<?php

namespace App\Livewire\Backoffice\Vas;

use App\Enums\VtuStatus;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\VtuOrder;
use App\Services\Vas\VtuPurchaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Bill Payments — Backoffice')]
class ListVtuOrders extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $service = 'all';

    #[Url]
    public string $status = 'all';

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function requeryOrder(string $id): void
    {
        $this->authorizeWrite();

        $order = VtuOrder::query()->find($id);
        if ($order === null) {
            return;
        }

        $fresh = app(VtuPurchaseService::class)->requery($order);

        $this->dispatch('toast', message: 'Order requeried — status: '.$fresh->status->getName(), type: 'info');
    }

    public function render(): View
    {
        $orders = VtuOrder::query()
            ->with('user')
            ->when($this->service !== 'all', fn (Builder $q) => $q->where('service', $this->service))
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('request_id', 'like', $term)
                    ->orWhere('billers_code', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('username', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->latest()
            ->paginate(25);

        return view('livewire.backoffice.vas.list-vtu-orders', [
            'orders' => $orders,
            'services' => [
                VtuOrder::SERVICE_AIRTIME => 'Airtime',
                VtuOrder::SERVICE_DATA => 'Data',
                VtuOrder::SERVICE_TV => 'Cable TV',
                VtuOrder::SERVICE_ELECTRICITY => 'Electricity',
                VtuOrder::SERVICE_EDUCATION => 'Education',
            ],
            'statuses' => VtuStatus::cases(),
            'detailOrder' => $this->detailId ? VtuOrder::query()->with('user')->find($this->detailId) : null,
        ]);
    }
}
