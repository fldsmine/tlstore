<?php

namespace App\Livewire\Backoffice\SocialFollows;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\SocialFollow;
use App\Models\SocialFollowProof;
use App\Models\SocialHandle;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.backoffice')]
#[Title('Social follows — Backoffice')]
class ManageSocialFollows extends Component
{
    use AuthorizesAdminActions, WithPagination;

    public const array PLATFORMS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'telegram' => 'Telegram',
        'youtube' => 'YouTube',
        'x' => 'X',
        'whatsapp' => 'WhatsApp',
        'other' => 'Other',
    ];

    #[Url]
    public string $tab = 'handles';

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $platform = 'instagram';

    public string $name = '';

    public string $handle = '';

    public string $url = '';

    public string $description = '';

    public string $sortOrder = '0';

    public bool $isActive = true;

    public function mount(): void
    {
        $this->tab = in_array($this->tab, ['handles', 'follows', 'proofs'], true) ? $this->tab : 'handles';
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['handles', 'follows', 'proofs'], true) ? $tab : 'handles';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'name', 'handle', 'url', 'description');
        $this->platform = 'instagram';
        $this->sortOrder = '0';
        $this->isActive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $socialHandle = SocialHandle::query()->findOrFail($id);

        $this->editingId = $socialHandle->id;
        $this->platform = $socialHandle->platform;
        $this->name = $socialHandle->name;
        $this->handle = $socialHandle->handle ?? '';
        $this->url = $socialHandle->url;
        $this->description = $socialHandle->description ?? '';
        $this->sortOrder = (string) $socialHandle->sort_order;
        $this->isActive = $socialHandle->is_active;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'platform' => ['required', 'in:'.implode(',', array_keys(self::PLATFORMS))],
            'name' => ['required', 'string', 'max:120'],
            'handle' => ['nullable', 'string', 'max:150'],
            'url' => ['required', 'string', 'url', 'regex:/^https?:\/\//i', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:65535'],
            'isActive' => ['boolean'],
        ], [
            'url.regex' => 'Use a public http or https link for this social page.',
        ]);

        $attributes = [
            'platform' => $this->platform,
            'name' => trim($this->name),
            'handle' => trim($this->handle) !== '' ? trim($this->handle) : null,
            'url' => trim($this->url),
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'sort_order' => (int) $this->sortOrder,
            'is_active' => $this->isActive,
        ];

        $isEditing = $this->editingId !== null;
        $targetChanged = false;

        if ($isEditing) {
            $socialHandle = SocialHandle::query()->findOrFail($this->editingId);
            $targetChanged = $socialHandle->platform !== $attributes['platform']
                || $socialHandle->handle !== $attributes['handle']
                || $socialHandle->url !== $attributes['url'];

            DB::transaction(function () use ($socialHandle, $attributes, $targetChanged): void {
                $socialHandle->update($attributes);

                if ($targetChanged) {
                    $socialHandle->userFollows()
                        ->whereNull('unfollowed_at')
                        ->update([
                            'unfollowed_at' => now(),
                            'unfollowed_by_admin_id' => auth('admin')->id(),
                        ]);
                }
            });
        } else {
            SocialHandle::query()->create([
                ...$attributes,
                'created_by_admin_id' => auth('admin')->id(),
            ]);
        }

        $this->showModal = false;
        $message = $isEditing
            ? ($targetChanged ? 'Social page updated; existing follow confirmations were reset.' : 'Social page updated.')
            : 'Social page added.';
        $this->dispatch('toast', message: $message, type: 'success');
    }

    /** Disabling a page removes it from the requirement without deleting its follow records. */
    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $socialHandle = SocialHandle::query()->findOrFail($id);
        $socialHandle->update(['is_active' => ! $socialHandle->is_active]);

        $this->dispatch('toast', message: $socialHandle->is_active
            ? 'Page enabled; users who have not followed it will be asked to do so.'
            : 'Page disabled; it no longer blocks dashboard access.', type: 'success');
    }

    /** Reset an individual confirmation while preserving the follow record for audit. */
    public function resetFollow(string $id): void
    {
        $this->authorizeWrite();
        $follow = SocialFollow::query()->findOrFail($id);

        if ($follow->unfollowed_at === null) {
            $follow->update([
                'unfollowed_at' => now(),
                'unfollowed_by_admin_id' => auth('admin')->id(),
            ]);
        }

        $this->dispatch('toast', message: 'Follow confirmation reset; the user will be prompted again.', type: 'success');
    }

    /** Documentation proof downloads are available to authenticated backoffice staff only. */
    public function downloadProof(string $id): ?StreamedResponse
    {
        abort_unless(auth('admin')->check(), 403);

        $proof = SocialFollowProof::query()->findOrFail($id);
        $disk = Storage::disk('social_proofs');
        abort_unless($disk->exists($proof->path), 404);

        return $disk->download($proof->path, basename($proof->original_name));
    }

    public function render(): View
    {
        if (! in_array($this->tab, ['handles', 'follows', 'proofs'], true)) {
            $this->tab = 'handles';
        }

        $search = trim($this->search);

        $handles = $this->tab === 'handles'
            ? SocialHandle::query()
                ->withCount(['userFollows as current_followers_count' => fn (Builder $query) => $query->whereNull('unfollowed_at')])
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%")
                    ->orWhere('handle', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15)
            : null;

        $follows = $this->tab === 'follows'
            ? SocialFollow::query()
                ->with(['user', 'socialHandle', 'removedByAdmin'])
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where(fn (Builder $fields) => $fields
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")))
                        ->orWhereHas('socialHandle', fn (Builder $handleQuery) => $handleQuery->where(fn (Builder $fields) => $fields
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('platform', 'like', "%{$search}%")
                            ->orWhere('handle', 'like', "%{$search}%")));
                }))
                ->latest('followed_at')
                ->paginate(20)
            : null;

        $proofs = $this->tab === 'proofs'
            ? SocialFollowProof::query()
                ->with(['user', 'socialHandle'])
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('original_name', 'like', "%{$search}%")
                        ->orWhere('target_url', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where(fn (Builder $fields) => $fields
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")))
                        ->orWhereHas('socialHandle', fn (Builder $handleQuery) => $handleQuery->where(fn (Builder $fields) => $fields
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('platform', 'like', "%{$search}%")
                            ->orWhere('handle', 'like', "%{$search}%")));
                }))
                ->latest()
                ->paginate(20)
            : null;

        return view('livewire.backoffice.social-follows.manage-social-follows', [
            'handles' => $handles,
            'follows' => $follows,
            'proofs' => $proofs,
            'activeHandleCount' => SocialHandle::query()->active()->count(),
            'currentFollowCount' => SocialFollow::query()->current()->count(),
            'proofCount' => SocialFollowProof::query()->count(),
        ]);
    }
}
