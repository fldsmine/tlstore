<?php

namespace App\Livewire\Backoffice\RewardTargets;

use App\Enums\RewardTargetStatus;
use App\Models\RewardTarget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Reward Targets — Backoffice')]
class ListRewardTargets extends Component
{
    use WithPagination;

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

    public function render(): View
    {
        $targets = RewardTarget::query()
            ->with('user')
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->whereHas('user', fn (Builder $u) => $u->where('username', 'like', '%'.trim($this->search).'%')))
            ->latest('date')
            ->paginate(20);

        return view('livewire.backoffice.reward-targets.list-reward-targets', [
            'targets' => $targets,
            'statuses' => RewardTargetStatus::cases(),
            'detailTarget' => $this->detailId ? RewardTarget::query()->with('user')->find($this->detailId) : null,
        ]);
    }
}
