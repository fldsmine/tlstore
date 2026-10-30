<?php

namespace App\Livewire\Backoffice\Admins;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Administrators — Backoffice')]
class ListAdmins extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    public bool $showDetail = false;

    public ?string $detailId = null;

    public ?string $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'regular';

    public $photo = null;

    public ?array $generatedCredentials = null;

    protected function authorizeManageAdmins(): void
    {
        abort_unless(auth('admin')->user()?->canManageAdmins() ?? false, 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorizeManageAdmins();
        $this->reset('editingId', 'name', 'email', 'photo');
        $this->role = 'regular';
        $this->generatedCredentials = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openDetail(string $id): void
    {
        $this->authorizeManageAdmins();
        $admin = Admin::query()->findOrFail($id);
        abort_if($admin->isSuper(), 403, 'The Super admin is protected.');

        $this->detailId = $admin->id;
        $this->showDetail = true;
    }

    public function edit(string $id): void
    {
        $this->authorizeManageAdmins();
        $admin = Admin::query()->findOrFail($id);
        abort_if($admin->isSuper(), 403, 'The Super admin cannot be edited here.');

        $this->showDetail = false;
        $this->editingId = $admin->id;
        $this->name = $admin->name;
        $this->email = $admin->email;
        $this->role = $admin->role->value;
        $this->photo = null;
        $this->generatedCredentials = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorizeManageAdmins();

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::in(array_map(fn (AdminRole $r) => $r->value, AdminRole::assignable()))],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $attributes = ['name' => $this->name, 'email' => $this->email, 'role' => $this->role];

        if ($this->photo) {
            $attributes['avatar'] = app(ImageCompressionService::class)->compressAndStore($this->photo, 'admin-avatars', null, 82, 512, 512);
        }

        if ($this->editingId) {
            $admin = Admin::query()->findOrFail($this->editingId);
            abort_if($admin->isSuper(), 403);
            $admin->update($attributes);
            $this->showModal = false;
            $this->dispatch('toast', message: 'Admin updated successfully', type: 'success');
        } else {
            $password = Str::password(16);
            Admin::query()->create($attributes + ['password' => Hash::make($password)]);
            $this->generatedCredentials = ['email' => $this->email, 'password' => $password];
            $this->showModal = false;
            $this->dispatch('toast', message: 'Admin created successfully — credentials shown below', type: 'success');
        }

        $this->photo = null;
    }

    public function resetPassword(string $id): void
    {
        $this->authorizeManageAdmins();
        $admin = Admin::query()->findOrFail($id);
        abort_if($admin->isSuper(), 403);

        $password = Str::password(16);
        $admin->update(['password' => Hash::make($password)]);
        $this->generatedCredentials = ['email' => $admin->email, 'password' => $password];
        $this->dispatch('toast', message: 'Password reset successfully — new credentials shown below', type: 'success');
    }

    public function delete(string $id): void
    {
        $this->authorizeManageAdmins();
        $admin = Admin::query()->findOrFail($id);
        abort_if($admin->isSuper(), 403, 'The Super admin cannot be deleted.');
        abort_if($admin->id === auth('admin')->id(), 403, 'You cannot delete yourself.');

        $admin->delete();
        $this->dispatch('toast', message: 'Admin deleted successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.admins.list-admins', [
            'admins' => Admin::query()->search($this->search)->latest()->paginate(15),
            'detailAdmin' => $this->detailId ? Admin::query()->find($this->detailId) : null,
        ]);
    }
}
