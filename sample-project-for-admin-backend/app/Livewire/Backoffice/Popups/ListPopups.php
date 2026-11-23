<?php

namespace App\Livewire\Backoffice\Popups;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Popup;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Marketing → Popups: the promotional dialogs shown over the user dashboard.
 *
 * Create, edit, enable/disable, delete, and the "recursive" switch: a recursive popup is due
 * again on every dashboard visit, a one-off is spent the first time it is displayed.
 *
 * The form carries a live preview built from the very same x-app.popup-card the dashboard uses,
 * so an admin sees exactly what users will get — including which optional pieces disappear.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Popups — Backoffice')]
class ListPopups extends Component
{
    use AuthorizesAdminActions, WithFileUploads, WithPagination;

    public bool $showModal = false;

    public ?string $editingId = null;

    // ── Content ──────────────────────────────────────────────
    public string $title = '';

    public string $heading = '';

    public string $body = '';

    public string $imageCaption = '';

    // ── Media ────────────────────────────────────────────────
    public $headerImage = null;

    public $backgroundImage = null;

    public ?string $existingHeaderUrl = null;

    public ?string $existingBackgroundUrl = null;

    /** Stored paths, kept so a replaced upload can be cleaned up. */
    public ?string $existingHeaderPath = null;

    public ?string $existingBackgroundPath = null;

    public bool $removeHeaderImage = false;

    public bool $removeBackgroundImage = false;

    // ── Actions ──────────────────────────────────────────────
    public string $primaryLabel = '';

    public string $primaryUrl = '';

    public string $secondaryLabel = '';

    public string $secondaryUrl = '';

    public string $footerText = '';

    // ── Behaviour ────────────────────────────────────────────
    public bool $isActive = true;

    public bool $isRecursive = true;

    public ?string $previewId = null;

    public function openCreate(): void
    {
        $this->authorizeWrite();

        $this->reset(
            'editingId', 'title', 'heading', 'body', 'imageCaption',
            'headerImage', 'backgroundImage', 'existingHeaderUrl', 'existingBackgroundUrl',
            'existingHeaderPath', 'existingBackgroundPath', 'removeHeaderImage', 'removeBackgroundImage',
            'primaryLabel', 'primaryUrl', 'secondaryLabel', 'secondaryUrl', 'footerText',
        );

        $this->isActive = true;
        $this->isRecursive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();

        $popup = Popup::query()->findOrFail($id);

        $this->editingId = $popup->id;
        $this->title = $popup->title ?? '';
        $this->heading = $popup->heading ?? '';
        $this->body = $popup->body;
        $this->imageCaption = $popup->image_caption ?? '';
        $this->headerImage = null;
        $this->backgroundImage = null;
        $this->existingHeaderUrl = $popup->header_image_url;
        $this->existingBackgroundUrl = $popup->background_image_url;
        $this->existingHeaderPath = $popup->header_image;
        $this->existingBackgroundPath = $popup->background_image;
        $this->removeHeaderImage = false;
        $this->removeBackgroundImage = false;
        $this->primaryLabel = $popup->primary_label ?? '';
        $this->primaryUrl = $popup->primary_url ?? '';
        $this->secondaryLabel = $popup->secondary_label ?? '';
        $this->secondaryUrl = $popup->secondary_url ?? '';
        $this->footerText = $popup->footer_text ?? '';
        $this->isActive = (bool) $popup->is_active;
        $this->isRecursive = (bool) $popup->is_recursive;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'title' => ['nullable', 'string', 'max:80'],
            'heading' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
            'imageCaption' => ['nullable', 'string', 'max:80'],
            'headerImage' => ['nullable', 'image', 'max:4096'],
            'backgroundImage' => ['nullable', 'image', 'max:4096'],
            // In-app paths (/dashboard/plans) and absolute links are both fine; nothing else is.
            'primaryUrl' => ['nullable', 'string', 'max:255', 'regex:#^(https?://|/)#i'],
            'primaryLabel' => ['nullable', 'string', 'max:60', 'required_with:primaryUrl'],
            'secondaryUrl' => ['nullable', 'string', 'max:255', 'regex:#^(https?://|/)#i'],
            'secondaryLabel' => ['nullable', 'string', 'max:60', 'required_with:secondaryUrl'],
            'footerText' => ['nullable', 'string', 'max:160'],
        ], [
            'primaryLabel.required_with' => 'Give the first link a label, or clear its URL.',
            'secondaryLabel.required_with' => 'Give the second link a label, or clear its URL.',
            'primaryUrl.regex' => 'Use a full link (https://…) or an in-app path starting with /.',
            'secondaryUrl.regex' => 'Use a full link (https://…) or an in-app path starting with /.',
        ]);

        $attributes = [
            'title' => $this->trimmed($this->title),
            'heading' => $this->trimmed($this->heading),
            'body' => $this->body,
            'image_caption' => $this->trimmed($this->imageCaption),
            'primary_label' => $this->trimmed($this->primaryLabel),
            'primary_url' => $this->trimmed($this->primaryUrl),
            'secondary_label' => $this->trimmed($this->secondaryLabel),
            'secondary_url' => $this->trimmed($this->secondaryUrl),
            'footer_text' => $this->trimmed($this->footerText),
            'is_active' => $this->isActive,
            'is_recursive' => $this->isRecursive,
        ];

        if ($this->headerImage) {
            $attributes['header_image'] = app(ImageCompressionService::class)
                ->compressAndStore($this->headerImage, 'popup-images', null, 82, 1600, null);
        } elseif ($this->removeHeaderImage) {
            $attributes['header_image'] = null;
        }

        if ($this->backgroundImage) {
            $attributes['background_image'] = app(ImageCompressionService::class)
                ->compressAndStore($this->backgroundImage, 'popup-images', null, 82, 1920, null);
        } elseif ($this->removeBackgroundImage) {
            $attributes['background_image'] = null;
        }

        if ($this->editingId) {
            $popup = Popup::query()->findOrFail($this->editingId);

            if (array_key_exists('header_image', $attributes) && $popup->header_image !== $attributes['header_image']) {
                $this->deleteStored($popup->header_image);
            }

            if (array_key_exists('background_image', $attributes) && $popup->background_image !== $attributes['background_image']) {
                $this->deleteStored($popup->background_image);
            }

            $popup->update($attributes);
        } else {
            Popup::query()->create($attributes);
        }

        $this->showModal = false;
        $this->reset('editingId', 'headerImage', 'backgroundImage', 'existingHeaderUrl', 'existingBackgroundUrl', 'existingHeaderPath', 'existingBackgroundPath', 'removeHeaderImage', 'removeBackgroundImage');

        $this->dispatch('toast', message: 'Popup saved', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();

        $popup = Popup::query()->findOrFail($id);
        $popup->update(['is_active' => ! $popup->is_active]);

        $this->dispatch('toast', message: $popup->is_active ? 'Popup enabled' : 'Popup disabled', type: 'success');
    }

    public function toggleRecursive(string $id): void
    {
        $this->authorizeWrite();

        $popup = Popup::query()->findOrFail($id);
        $popup->update(['is_recursive' => ! $popup->is_recursive]);

        $this->dispatch('toast', message: $popup->is_recursive
            ? 'Popup will show again on every visit'
            : 'Popup will show once per user', type: 'success');
    }

    public function preview(string $id): void
    {
        $this->authorizeWrite();
        $this->previewId = $id;
    }

    /** Drop the header image from the form — a fresh upload, or the stored one. */
    public function clearHeaderImage(): void
    {
        $this->authorizeWrite();

        $this->headerImage = null;
        $this->removeHeaderImage = true;
    }

    public function keepHeaderImage(): void
    {
        $this->authorizeWrite();

        $this->removeHeaderImage = false;
    }

    /** Drop the background image from the form — a fresh upload, or the stored one. */
    public function clearBackgroundImage(): void
    {
        $this->authorizeWrite();

        $this->backgroundImage = null;
        $this->removeBackgroundImage = true;
    }

    public function keepBackgroundImage(): void
    {
        $this->authorizeWrite();

        $this->removeBackgroundImage = false;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();

        $popup = Popup::query()->findOrFail($id);

        $this->safeDelete(function () use ($popup): void {
            $popup->delete();
        }, 'popup', 'Popup deleted');
    }

    public function render(): View
    {
        return view('livewire.backoffice.popups.list-popups', [
            'popups' => Popup::query()
                ->withCount('views')
                ->withCount(['views as dismissals_count' => fn (Builder $q) => $q->whereNotNull('dismissed_at')])
                ->latest()
                ->paginate(15),
            'preview' => $this->previewId ? Popup::query()->find($this->previewId) : null,
        ]);
    }

    /** A trimmed string, or null so the column stays empty (which is what hides the section). */
    protected function trimmed(string $value): ?string
    {
        return trim($value) !== '' ? trim($value) : null;
    }

    /** Remove a replaced upload, never an external or root-relative path. */
    protected function deleteStored(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://', '//', '/'])) {
            return;
        }

        try {
            Storage::disk(config('filesystems.default'))->delete($path);
        } catch (\Throwable) {
            // A missing or unreadable file must never block saving the popup.
        }
    }
}