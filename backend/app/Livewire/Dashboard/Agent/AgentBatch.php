<?php

namespace App\Livewire\Dashboard\Agent;

use App\Models\Coupon;
use App\Models\CouponBatch;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Token Batch')]
class AgentBatch extends Component
{
    use WithPagination;

    public string $batchId = '';

    public ?string $viewing = null;

    public string $search = '';

    public string $statusFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function mount(CouponBatch $batch): void
    {
        $user = auth()->user();

        if (! $user->is_agent || $batch->agent_id !== $user->id) {
            $this->redirectRoute('agent.dashboard', navigate: true);

            return;
        }

        $this->batchId = $batch->id;
    }

    public function viewToken(string $couponId): void
    {
        $this->viewing = $couponId;
    }

    public function closeToken(): void
    {
        $this->viewing = null;
    }

    protected function batch(): ?CouponBatch
    {
        return CouponBatch::query()
            ->where('agent_id', auth()->id())
            ->with(['package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->find($this->batchId);
    }

    public function render(): View
    {
        $batch = $this->batch();

        if (! $batch) {
            return view('livewire.dashboard.agent.agent-batch', ['batch' => null]);
        }

        $agg = $batch->coupons()
            ->selectRaw('count(*) as total, count(user_id) as used')
            ->first();
        $total = (int) $agg->total;
        $used = (int) $agg->used;

        $coupons = $batch->coupons()
            ->when($this->search !== '', fn ($q) => $q->where('code', 'like', '%'.trim($this->search).'%'))
            ->when($this->statusFilter === 'active', fn ($q) => $q->whereNull('user_id'))
            ->when($this->statusFilter === 'used', fn ($q) => $q->whereNotNull('user_id'))
            ->with(['package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->latest()
            ->paginate(15);

        $viewingCoupon = $this->viewing
            ? Coupon::query()->where('agent_id', auth()->id())->with(['package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan', 'redeemer'])->find($this->viewing)
            : null;

        return view('livewire.dashboard.agent.agent-batch', [
            'batch' => $batch,
            'planName' => $batch->displayName(),
            'coupons' => $coupons,
            'stats' => ['total' => $total, 'active' => $total - $used, 'used' => $used],
            'viewingCoupon' => $viewingCoupon,
        ]);
    }
}
