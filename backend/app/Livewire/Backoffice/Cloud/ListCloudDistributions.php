<?php

namespace App\Livewire\Backoffice\Cloud;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CloudDistribution;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Bonus Distributions — Backoffice')]
class ListCloudDistributions extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.backoffice.cloud.list-cloud-distributions', [
            'distributions' => CloudDistribution::query()
                ->withCount('claims')
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('sender_name', 'like', '%'.trim($this->search).'%'))
                ->when(in_array($this->type, [CloudDistribution::TYPE_PROMOTER, CloudDistribution::TYPE_SHAREHOLDER], true),
                    fn (Builder $q) => $q->where('type', $this->type))
                ->latest()
                ->paginate(20),
        ]);
    }
}
