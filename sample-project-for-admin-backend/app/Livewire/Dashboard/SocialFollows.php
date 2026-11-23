<?php

namespace App\Livewire\Dashboard;

use App\Models\SocialFollow;
use App\Models\SocialFollowProof;
use App\Models\SocialHandle;
use App\Services\SocialFollowRequirement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class SocialFollows extends Component
{
    use WithFileUploads;

    /** The modal may be opened manually when there are no pending requirements. */
    public bool $isOpen = false;

    /** @var array<string, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $proofs = [];

    public function mount(): void
    {
        $this->isOpen = request()->boolean('showSocialFollows');
    }

    #[On('open-social-follows')]
    public function open(): void
    {
        $this->isOpen = true;
    }

    /** Confirm the self-attested follow independently of any optional proof document. */
    public function confirmFollow(string $handleId): void
    {
        $user = auth()->user();
        $handle = SocialHandle::query()->active()->findOrFail($handleId);

        DB::transaction(function () use ($user, $handle): void {
            $follow = SocialFollow::query()->firstOrNew([
                'user_id' => $user->id,
                'social_handle_id' => $handle->id,
            ]);

            $follow->fill([
                'followed_at' => now(),
                'unfollowed_at' => null,
                'unfollowed_by_admin_id' => null,
            ]);
            $follow->save();
        });

        $this->dispatch('toast', message: 'Your follow confirmation has been saved.', type: 'success');

        if (! app(SocialFollowRequirement::class)->hasPending($user)) {
            $this->isOpen = false;
        }
    }

    /** Save optional documentation separately; this never creates or changes a follow confirmation. */
    public function uploadProof(string $handleId): void
    {
        $user = auth()->user();
        $handle = SocialHandle::query()->active()->findOrFail($handleId);
        $field = 'proofs.'.$handleId;

        $this->validate([
            $field => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $file = $this->proofs[$handleId];
        $disk = Storage::disk('social_proofs');
        $oldPath = SocialFollowProof::query()
            ->where('user_id', $user->id)
            ->where('social_handle_id', $handle->id)
            ->value('path');
        $path = $file->store($user->id.'/'.$handle->id, 'social_proofs');

        if (! $path) {
            $this->addError($field, 'The proof could not be saved. Please try again.');

            return;
        }

        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $originalName = mb_substr($originalName !== '' ? $originalName : 'proof', 0, 255);

        try {
            SocialFollowProof::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'social_handle_id' => $handle->id,
                ],
                [
                    'path' => $path,
                    'original_name' => $originalName,
                    'target_url' => $handle->url,
                ],
            );
        } catch (\Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        if ($oldPath && $oldPath !== $path) {
            rescue(fn () => $disk->delete($oldPath), false);
        }

        unset($this->proofs[$handleId]);
        $this->resetValidation($field);
        $this->dispatch('toast', message: 'Proof saved for documentation only. Your follow confirmation is unchanged.', type: 'success');
    }

    public function markUnfollowed(string $handleId): void
    {
        $follow = SocialFollow::query()
            ->where('user_id', auth()->id())
            ->where('social_handle_id', $handleId)
            ->whereNull('unfollowed_at')
            ->whereHas('socialHandle', fn ($query) => $query->where('is_active', true))
            ->firstOrFail();

        $follow->update([
            'unfollowed_at' => now(),
            'unfollowed_by_admin_id' => null,
        ]);

        $this->dispatch('toast', message: 'This page is marked as no longer followed. Follow it again to keep dashboard access.', type: 'info');
    }

    public function close(): void
    {
        if (app(SocialFollowRequirement::class)->hasPending(auth()->user())) {
            return;
        }

        $this->isOpen = false;
    }

    public function render(): View
    {
        $user = auth()->user();
        $requirements = app(SocialFollowRequirement::class);
        $pendingHandles = $requirements->pendingHandlesFor($user)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $completedCount = SocialFollow::query()
            ->where('user_id', $user->id)
            ->whereNull('unfollowed_at')
            ->whereHas('socialHandle', fn ($query) => $query->where('is_active', true))
            ->count();
        $hasPending = $pendingHandles->isNotEmpty();
        $proofsByHandle = SocialFollowProof::query()
            ->where('user_id', $user->id)
            ->whereIn('social_handle_id', $pendingHandles->modelKeys())
            ->get()
            ->keyBy('social_handle_id');

        return view('livewire.dashboard.social-follows', [
            'pendingHandles' => $pendingHandles,
            'proofsByHandle' => $proofsByHandle,
            'requiredCount' => SocialHandle::query()->active()->count(),
            'completedCount' => $completedCount,
            'hasPending' => $hasPending,
            // This server-derived condition prevents a client-side close while any
            // requirement remains pending, even if the public component state is changed.
            'showModal' => $hasPending || $this->isOpen,
        ]);
    }
}
