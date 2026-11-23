<?php

namespace App\Livewire\Backoffice\Admins;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Administrator management. Super-admin only (route + every action re-checks).
 * Never allows disabling/demoting yourself or the last remaining super admin.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Administrators')]
class AdminsIndex extends Component
{
    use AuthorizesAdminActions;
    use WithPagination;

    #[Url]
    public string $search = '';

    // Create form
    public string $newName = '';
    public string $newEmail = '';
    public string $newRole = 'admin';
    public string $newPassword = '';
    public string $generatedPassword = '';

    public function mount(): void
    {
        $this->authorizeSuperAdmin();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $actor = $this->authorizeSuperAdmin();

        $data = $this->validate([
            'newName' => ['required', 'string', 'max:180'],
            'newEmail' => ['required', 'email:rfc', 'max:191', Rule::unique('admins', 'email')],
            'newRole' => ['required', Rule::in(['admin', 'super_admin'])],
            'newPassword' => ['nullable', 'string', 'min:12', 'max:255'],
        ]);

        $password = $data['newPassword'] !== '' ? $data['newPassword'] : Str::password(18);

        $admin = Admin::query()->create([
            'full_name' => $data['newName'],
            'email' => strtolower($data['newEmail']),
            'password' => $password, // hashed cast
            'role' => $data['newRole'],
            'status' => 'active',
        ]);

        AuditLog::query()->create([
            'actor_admin_id' => $actor->id,
            'action' => 'admin.created',
            'entity_type' => 'admin',
            'entity_id' => (string) $admin->id,
            'after_values' => ['email' => $admin->email, 'role' => $admin->role],
            'created_at' => now(),
        ]);

        $this->generatedPassword = $data['newPassword'] !== '' ? '' : $password;
        $this->reset(['newName', 'newEmail', 'newRole', 'newPassword']);
        $this->dispatch('toast', message: 'Administrator created.', type: 'success');
    }

    public function dismissGeneratedPassword(): void
    {
        $this->authorizeSuperAdmin();
        $this->generatedPassword = '';
    }

    public function toggleStatus(int $id): void
    {
        $actor = $this->authorizeSuperAdmin();
        $admin = Admin::query()->findOrFail($id);

        if ($admin->id === $actor->id) {
            $this->dispatch('toast', message: 'You cannot disable your own account.', type: 'warning');

            return;
        }
        if ($admin->status === 'active' && $admin->isSuperAdmin() && $this->superAdminCount() <= 1) {
            $this->dispatch('toast', message: 'The last active super administrator cannot be disabled.', type: 'warning');

            return;
        }

        $admin->status = $admin->status === 'active' ? 'disabled' : 'active';
        if ($admin->status !== 'active') {
            // Force remember-me re-auth on all devices.
            $admin->forceFill(['remember_token' => Str::random(60)])->save();
        } else {
            $admin->save();
        }

        AuditLog::query()->create([
            'actor_admin_id' => $actor->id,
            'action' => 'admin.status_changed',
            'entity_type' => 'admin',
            'entity_id' => (string) $admin->id,
            'after_values' => ['status' => $admin->status],
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: "Administrator {$admin->status === 'active' ? 'enabled' : 'disabled'}.", type: 'success');
    }

    public function toggleRole(int $id): void
    {
        $actor = $this->authorizeSuperAdmin();
        $admin = Admin::query()->findOrFail($id);

        if ($admin->id === $actor->id) {
            $this->dispatch('toast', message: 'You cannot change your own role.', type: 'warning');

            return;
        }
        if ($admin->isSuperAdmin() && $this->superAdminCount() <= 1) {
            $this->dispatch('toast', message: 'The last super administrator cannot be demoted.', type: 'warning');

            return;
        }

        $admin->role = $admin->isSuperAdmin() ? 'admin' : 'super_admin';
        $admin->save();

        AuditLog::query()->create([
            'actor_admin_id' => $actor->id,
            'action' => 'admin.role_changed',
            'entity_type' => 'admin',
            'entity_id' => (string) $admin->id,
            'after_values' => ['role' => $admin->role],
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: 'Administrator role updated.', type: 'success');
    }

    public function resetPassword(int $id): void
    {
        $actor = $this->authorizeSuperAdmin();
        $admin = Admin::query()->findOrFail($id);

        $password = Str::password(18);
        $admin->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        AuditLog::query()->create([
            'actor_admin_id' => $actor->id,
            'action' => 'admin.password_reset',
            'entity_type' => 'admin',
            'entity_id' => (string) $admin->id,
            'after_values' => ['reset_by_admin_id' => $actor->id],
            'created_at' => now(),
        ]);

        $this->generatedPassword = $password;
        $this->dispatch('toast', message: 'Password reset. The new password is shown once below.', type: 'success');
    }

    private function superAdminCount(): int
    {
        return Admin::query()
            ->where('status', 'active')
            ->whereIn('role', ['super_admin', 'super-admin', 'super'])
            ->count();
    }

    public function render(): View
    {
        $this->authorizeSuperAdmin();

        $search = trim($this->search);

        $admins = Admin::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($rows) => $rows
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('livewire.backoffice.admins.admins-index', [
            'admins' => $admins,
        ]);
    }
}
