<?php

namespace App\Livewire\Backoffice\CallIds;

use App\Models\AllocatedNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Call IDs — Backoffice')]
class ListCallIds extends Component
{
    use WithPagination;

    #[Url]
    public string $source = 'all';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = AllocatedNumber::query()->findOrFail($id)->id;
        $this->showDetail = true;
    }

    public function render(): View
    {
        $numbers = AllocatedNumber::query()
            ->with('user')
            ->when($this->source !== 'all', fn (Builder $q) => $q->where('source', $this->source))
            ->when($this->status === 'active', fn (Builder $q) => $q->active())
            ->when($this->status === 'expired', fn (Builder $q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', now()))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('username', 'like', '%'.trim($this->search).'%')->orWhere('email', 'like', '%'.trim($this->search).'%')))
            ->latest()
            ->paginate(25);

        return view('livewire.backoffice.call-ids.list-call-ids', [
            'numbers' => $numbers,
            'detailNumber' => $this->detailId ? AllocatedNumber::query()->with('user')->find($this->detailId) : null,
        ]);
    }
}
