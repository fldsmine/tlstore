<?php

namespace App\Livewire\Backoffice\PayLater;

use App\Enums\PayLaterStatus;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\MinuteCredit;
use App\Services\PayLaterService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Pay Later — Backoffice')]
class ListPayLater extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public bool $showDetail = false;

    public ?string $detailId = null;

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

    public function markRepaid(string $id): void
    {
        $this->authorizeWrite();
        $credit = MinuteCredit::query()->findOrFail($id);
        if ($credit->status === PayLaterStatus::ACTIVE) {
            $credit->update(['status' => PayLaterStatus::REPAID->value, 'repaid_at' => now()]);
            $this->dispatch('toast', message: 'Marked as paid successfully', type: 'success');
        }
        $this->showDetail = false;
    }

    public function markDefaulted(string $id): void
    {
        $this->authorizeWrite();
        $credit = MinuteCredit::query()->findOrFail($id);
        if ($credit->status === PayLaterStatus::ACTIVE) {

            app(PayLaterService::class)->forfeit($credit);
            $this->dispatch('toast', message: 'Marked as defaulted successfully', type: 'success');
        }
        $this->showDetail = false;
    }

    public function extendDue(string $id): void
    {
        $this->authorizeWrite();
        $credit = MinuteCredit::query()->findOrFail($id);

        app(PayLaterService::class)->extend($credit, 14);
        $this->dispatch('toast', message: 'Due date and minutes extended successfully', type: 'success');
        $this->showDetail = false;
    }

    public function render(): View
    {
        $credits = MinuteCredit::query()
            ->with(['user', 'plan'])
            ->when($this->status === 'overdue', fn (Builder $q) => $q->where('status', PayLaterStatus::ACTIVE->value)->whereDate('due_date', '<', today()))
            ->when(in_array($this->status, ['active', 'repaid', 'defaulted'], true), fn (Builder $q) => $q->where('status', $this->status))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('username', 'like', '%'.trim($this->search).'%')))
            ->latest()
            ->paginate(20);

        return view('livewire.backoffice.pay-later.list-pay-later', [
            'credits' => $credits,
            'detailCredit' => $this->detailId ? MinuteCredit::query()->with(['user', 'plan'])->find($this->detailId) : null,
        ]);
    }
}
