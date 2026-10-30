<?php

namespace App\Livewire\Backoffice\Announcements;

use App\Enums\AnnouncementType;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Announcement;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Announcements — Backoffice')]
class ListAnnouncements extends Component
{
    use AuthorizesAdminActions, WithPagination;

    public bool $showModal = false;

    public ?string $editingId = null;

    public string $title = '';

    public string $body = '';

    public string $type = 'info';

    public bool $isActive = true;

    public string $actionUrl = '';

    public string $actionLabel = '';

    public bool $isDismissible = true;

    public string $startsAt = '';

    public string $endsAt = '';

    public function openCreate(): void
    {
        $this->authorizeWrite();
        $this->reset('editingId', 'title', 'body', 'actionUrl', 'actionLabel', 'startsAt', 'endsAt');
        $this->type = 'info';
        $this->isActive = true;
        $this->isDismissible = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeWrite();
        $a = Announcement::query()->findOrFail($id);
        $this->editingId = $a->id;
        $this->title = $a->title;
        $this->body = $a->body;
        $this->type = $a->type->value;
        $this->isActive = (bool) $a->is_active;
        $this->actionUrl = $a->action_url ?? '';
        $this->actionLabel = $a->action_label ?? '';
        $this->isDismissible = (bool) $a->is_dismissible;
        $this->startsAt = $a->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = $a->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'title' => ['required', 'string', 'max:140'],
            'body' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'in:'.implode(',', AnnouncementType::values())],
            'actionUrl' => ['nullable', 'url', 'max:255'],
            'actionLabel' => ['nullable', 'string', 'max:60', 'required_with:actionUrl'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
        ], [
            'actionLabel.required_with' => 'Give the button a label when you set a link.',
        ]);

        $attributes = [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'is_active' => $this->isActive,
            'action_url' => $this->actionUrl ?: null,
            'action_label' => $this->actionUrl ? $this->actionLabel : null,
            'is_dismissible' => $this->isDismissible,
            'starts_at' => $this->startsAt ?: null,
            'ends_at' => $this->endsAt ?: null,
        ];

        if ($this->editingId) {
            Announcement::query()->whereKey($this->editingId)->update($attributes);
        } else {
            Announcement::query()->create($attributes);
        }

        $this->showModal = false;
        $this->dispatch('toast', message: 'Announcement saved successfully', type: 'success');
    }

    public function toggleActive(string $id): void
    {
        $this->authorizeWrite();
        $a = Announcement::query()->findOrFail($id);
        $a->update(['is_active' => ! $a->is_active]);
    }

    public function delete(string $id): void
    {
        $this->authorizeWrite();
        Announcement::query()->whereKey($id)->delete();
        $this->dispatch('toast', message: 'Announcement deleted successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.announcements.list-announcements', [
            'announcements' => Announcement::query()->latest()->paginate(15),
            'types' => AnnouncementType::cases(),
        ]);
    }
}
