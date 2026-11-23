<?php

namespace App\Livewire\Backoffice\CallRecords;

use App\Models\CallRecord;
use App\Models\CallType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Call Logs — Backoffice')]
class ListCallRecords extends Component
{
    use WithPagination;

    #[Url]
    public string $callType = 'all';

    #[Url]
    public string $direction = 'all';

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

    public function updatedDirection(): void
    {
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function render(): View
    {
        $records = CallRecord::query()
            ->with('callType')
            ->withCount('claims')
            ->when($this->callType !== 'all', fn (Builder $q) => $q->where('call_type_id', $this->callType))
            ->when(in_array($this->direction, ['incoming', 'outgoing'], true), fn (Builder $q) => $q->where('direction', $this->direction))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('counterparty', 'like', '%'.trim($this->search).'%'))
            ->latest()
            ->paginate(25);

        return view('livewire.backoffice.call-records.list-call-records', [
            'records' => $records,
            'callTypes' => CallType::query()->orderBy('name')->get(),
            'detailRecord' => $this->detailId ? CallRecord::query()->with('callType')->withCount('claims')->find($this->detailId) : null,
        ]);
    }
}
