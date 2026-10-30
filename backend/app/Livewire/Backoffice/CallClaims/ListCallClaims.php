<?php

namespace App\Livewire\Backoffice\CallClaims;

use App\Models\CallClaim;
use App\Models\CallType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Call Claims — Backoffice')]
class ListCallClaims extends Component
{
    use WithPagination;

    #[Url]
    public string $callType = 'all';

    #[Url]
    public string $search = '';

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCallType(): void
    {
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = CallClaim::query()->findOrFail($id)->id;
        $this->showDetail = true;
    }

    public function render(): View
    {
        $claims = CallClaim::query()
            ->with(['user', 'callType'])
            ->when($this->callType !== 'all', fn (Builder $q) => $q->where('call_type_id', $this->callType))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('username', 'like', '%'.trim($this->search).'%')->orWhere('email', 'like', '%'.trim($this->search).'%')))
            ->latest('claimed_at')
            ->paginate(25);

        return view('livewire.backoffice.call-claims.list-call-claims', [
            'claims' => $claims,
            'callTypes' => CallType::query()->orderBy('name')->get(),
            'detailClaim' => $this->detailId ? CallClaim::query()->with(['user', 'callType'])->find($this->detailId) : null,
        ]);
    }
}
