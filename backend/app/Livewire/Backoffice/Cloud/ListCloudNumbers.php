<?php

namespace App\Livewire\Backoffice\Cloud;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CloudNumber;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Sponsor Numbers — Backoffice')]
class ListCloudNumbers extends Component
{
    use AuthorizesAdminActions, WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $lineNumber = '';

    public string $brand = '';

    public string $callerName = '';

    public string $popupMessage = '';

    public string $claimUrl = '';

    public $avatar = null;

    public ?string $existingAvatarUrl = null;

    public string $earningPerCall = '';

    public string $sortOrder = '0';

    public bool $isActive = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'name', 'lineNumber', 'brand', 'callerName', 'popupMessage', 'claimUrl', 'avatar', 'existingAvatarUrl', 'earningPerCall');
        $this->sortOrder = '0';
        $this->isActive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $number = CloudNumber::query()->findOrFail($id);
        $this->editingId = $number->id;
        $this->name = (string) $number->name;
        $this->lineNumber = (string) $number->line_number;
        $this->brand = (string) $number->brand;
        $this->callerName = (string) $number->caller_name;
        $this->popupMessage = (string) $number->popup_message;
        $this->claimUrl = (string) $number->claim_url;
        $this->avatar = null;
        $this->existingAvatarUrl = $number->avatar_url;
        $this->earningPerCall = (string) $number->earning_per_call;
        $this->sortOrder = (string) $number->sort_order;
        $this->isActive = (bool) $number->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'lineNumber' => ['required', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'callerName' => ['nullable', 'string', 'max:120'],
            'popupMessage' => ['nullable', 'string', 'max:500'],
            'claimUrl' => ['nullable', 'url', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'earningPerCall' => ['required', 'numeric', 'min:0'],
            'sortOrder' => ['required', 'integer', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        $attributes = [
            'name' => $this->name,
            'line_number' => $this->lineNumber,
            'brand' => $this->brand ?: null,
            'caller_name' => $this->callerName ?: null,
            'popup_message' => $this->popupMessage ?: null,
            'claim_url' => $this->claimUrl ?: null,
            'earning_per_call' => $this->earningPerCall,
            'sort_order' => (int) $this->sortOrder,
            'is_active' => $this->isActive,
        ];

        if ($this->avatar) {
            $attributes['avatar'] = app(ImageCompressionService::class)->compressAndStore($this->avatar, 'cloud-avatars', null, 82, 512, 512);
        }

        if ($this->editingId) {
            $number = CloudNumber::query()->findOrFail($this->editingId);
            $oldAvatar = $number->avatar;
            $number->update($attributes);

            if ($this->avatar && $oldAvatar && $oldAvatar !== $number->avatar) {
                Storage::delete($oldAvatar);
            }
        } else {
            $number = CloudNumber::query()->create($attributes);
        }

        $this->avatar = null;
        $this->showModal = false;
        $this->dispatch('toast', message: 'Sponsor number saved successfully', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $number = CloudNumber::query()->findOrFail($id);
        $number->update(['is_active' => ! $number->is_active]);
        $this->dispatch('toast', message: $number->is_active ? 'Sponsor number enabled successfully' : 'Sponsor number disabled successfully', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        $number = CloudNumber::query()->findOrFail($id);

        $this->safeDelete(function () use ($number): void {

            $avatar = $number->avatar;
            $number->delete();
            if ($avatar) {
                Storage::delete($avatar);
            }
        }, 'sponsor number');
    }

    public function render(): View
    {
        return view('livewire.backoffice.cloud.list-cloud-numbers', [
            'numbers' => CloudNumber::query()
                ->withCount('notes')
                ->when(trim($this->search) !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', '%'.trim($this->search).'%')
                    ->orWhere('brand', 'like', '%'.trim($this->search).'%')
                    ->orWhere('line_number', 'like', '%'.trim($this->search).'%')))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }
}
