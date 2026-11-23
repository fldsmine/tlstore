<?php

namespace App\Livewire\Backoffice\Ads;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Ad;
use App\Models\AdShare;
use App\Services\AdService;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Ads — Backoffice')]
class ListAds extends Component
{
    use AuthorizesAdminActions, WithFileUploads, WithPagination;

    #[Url]
    public string $tab = 'ads';

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $advertiser = '';

    public string $description = '';

    public $image = null;

    public ?string $existingImageUrl = null;

    public string $targetUrl = '';

    public string $reward = '';

    public string $impressions = '';

    public string $views = '';

    public string $growthPercent = '';

    public string $growthIntervalHours = '';

    public bool $isActive = true;

    public bool $showDetail = false;

    public ?string $detailId = null;

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['ads', 'shares'], true) ? $tab : 'ads';
        $this->showDetail = false;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openDetail(string $id): void
    {

        $this->detailId = Ad::query()->findOrFail($id)->id;
        $this->showDetail = true;
    }

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'name', 'advertiser', 'description', 'image', 'existingImageUrl', 'targetUrl', 'reward', 'impressions', 'views', 'growthPercent', 'growthIntervalHours');
        $this->isActive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $ad = Ad::query()->findOrFail($id);
        $this->editingId = $ad->id;
        $this->name = (string) $ad->name;
        $this->advertiser = (string) $ad->advertiser;
        $this->description = (string) $ad->description;
        $this->image = null;
        $this->existingImageUrl = $ad->image_url;
        $this->targetUrl = (string) $ad->target_url;
        $this->reward = (string) $ad->reward;
        $this->impressions = (string) $ad->impressions;
        $this->views = (string) $ad->views;
        $this->growthPercent = (string) $ad->growth_percent;
        $this->growthIntervalHours = (string) $ad->growth_interval_hours;
        $this->isActive = (bool) $ad->is_active;
        $this->showDetail = false;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'advertiser' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:280'],
            'image' => ['nullable', 'image', 'max:4096'],
            'targetUrl' => ['nullable', 'url', 'max:500'],
            'reward' => ['required', 'numeric', 'min:0'],
            'impressions' => ['required', 'integer', 'min:0'],
            'views' => ['required', 'integer', 'min:0'],
            'growthPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'growthIntervalHours' => ['required', 'integer', 'min:1', 'max:168'],
        ]);

        $attributes = [
            'name' => $this->name,
            'advertiser' => $this->advertiser,
            'description' => $this->description ?: null,
            'target_url' => $this->targetUrl ?: null,
            'reward' => $this->reward,
            'base_reward' => $this->reward,
            'impressions' => (int) $this->impressions,
            'views' => (int) $this->views,
            'growth_percent' => (float) $this->growthPercent,
            'growth_interval_hours' => (int) $this->growthIntervalHours,
            'is_active' => $this->isActive,
        ];

        if ($this->image) {
            $attributes['image_url'] = app(ImageCompressionService::class)->compressAndStore($this->image, 'ad-images', null, 78, 1280, null);
        }

        if ($this->editingId) {
            Ad::query()->whereKey($this->editingId)->update($attributes);
        } else {
            Ad::query()->create($attributes + ['version' => 1, 'shares' => 0, 'last_grown_at' => null]);
        }

        $this->image = null;
        $this->showModal = false;
        $this->dispatch('toast', message: 'Ad saved successfully', type: 'success');
    }

    public function resetReward(string $id): void
    {
        $this->authorizeWrite();
        $ad = Ad::query()->findOrFail($id);
        app(AdService::class)->resetReward($ad);
        $this->dispatch('toast', message: 'Reward reset to '.money((float) $ad->base_reward).'.', type: 'success');
    }

    public function bumpVersion(string $id): void
    {
        $this->authorizeWrite();
        Ad::query()->whereKey($id)->increment('version');
        $this->dispatch('toast', message: 'Ad version bumped successfully', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $ad = Ad::query()->findOrFail($id);
        $ad->update(['is_active' => ! $ad->is_active]);
        $this->dispatch('toast', message: $ad->is_active ? 'Ad enabled successfully' : 'Ad disabled successfully', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        Ad::query()->whereKey($id)->delete();
        $this->showDetail = false;
        $this->dispatch('toast', message: 'Ad deleted successfully', type: 'success');
    }

    public function render(): View
    {
        $term = '%'.trim($this->search).'%';
        $ads = null;
        $shares = null;

        if ($this->tab === 'shares') {
            $shares = AdShare::query()
                ->with(['user', 'ad'])
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->whereHas('user', fn (Builder $u) => $u->where('username', 'like', $term))
                    ->orWhereHas('ad', fn (Builder $a) => $a->where('name', 'like', $term))))
                ->latest('shared_at')
                ->paginate(25);
        } else {
            $ads = Ad::query()
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', $term)
                    ->orWhere('advertiser', 'like', $term)))
                ->latest()
                ->paginate(20);
        }

        return view('livewire.backoffice.ads.list-ads', [
            'tab' => $this->tab,
            'ads' => $ads,
            'shares' => $shares,
            'adsCount' => Ad::query()->count(),
            'sharesCount' => AdShare::query()->count(),
            'detailAd' => $this->detailId ? Ad::query()->find($this->detailId) : null,
        ]);
    }
}
