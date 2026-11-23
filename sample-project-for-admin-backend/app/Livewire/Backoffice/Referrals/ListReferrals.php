<?php

namespace App\Livewire\Backoffice\Referrals;

use App\Enums\ReferralStatus;
use App\Models\Referral;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Referrals — Backoffice')]
class ListReferrals extends Component
{
    use WithPagination;

    #[Url]
    public string $level = 'direct';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    public ?string $selectedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setLevel(string $level): void
    {
        $this->level = in_array($level, ['direct', 'indirect'], true) ? $level : 'direct';
        $this->selectedId = null;
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function show(string $id): void
    {
        $this->selectedId = $id;
    }

    public function close(): void
    {
        $this->selectedId = null;
    }

    public function render(): View
    {
        $query = Referral::query()
            ->when($this->status !== 'all', fn (Builder $q) => $q->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $q) => $q
                    ->where('reference', 'like', $term)
                    ->orWhereHas('referrer', fn (Builder $u) => $u->where('username', 'like', $term))
                    ->orWhereHas('referee', fn (Builder $u) => $u->where('username', 'like', $term)));
            });

        if ($this->level === 'indirect') {
            $query->whereHas('referrer', fn (Builder $q) => $q->whereNotNull('referrer_id'))
                ->with(['referrer.referrer', 'referrer', 'referee']);
        } else {
            $query->with(['referrer', 'referee']);
        }

        return view('livewire.backoffice.referrals.list-referrals', [
            'referrals' => $query->latest()->paginate(20),
            'statuses' => ReferralStatus::cases(),
            'level' => $this->level,
            'directCount' => Referral::query()->count(),
            'indirectCount' => Referral::query()->whereHas('referrer', fn (Builder $q) => $q->whereNotNull('referrer_id'))->count(),
            'selected' => $this->selectedId
                ? Referral::query()->with(['referrer.referrer', 'referrer.package', 'referee.package'])->find($this->selectedId)
                : null,
        ]);
    }
}
