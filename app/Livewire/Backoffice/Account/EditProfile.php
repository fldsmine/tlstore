<?php

namespace App\Livewire\Backoffice\Account;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('My profile')]
class EditProfile extends Component
{
    use AuthorizesAdminActions;

    public string $fullName = '';
    public string $email = '';

    public function mount(): void
    {
        $admin = $this->authorizeAdmin();
        $this->fullName = (string) $admin->full_name;
        $this->email = (string) $admin->email;
    }

    public function save(): void
    {
        $admin = $this->authorizeAdmin();

        $data = $this->validate([
            'fullName' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('admins', 'email')->ignore($admin->id)],
        ]);

        $before = ['full_name' => $admin->full_name, 'email' => $admin->email];

        $admin->forceFill([
            'full_name' => $data['fullName'],
            'email' => strtolower(trim($data['email'])),
        ])->save();

        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => 'admin.profile_updated',
            'entity_type' => 'admin',
            'entity_id' => (string) $admin->id,
            'before_values' => $before,
            'after_values' => ['full_name' => $admin->full_name, 'email' => $admin->email],
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: 'Profile saved.', type: 'success');
    }

    public function render(): View
    {
        $admin = $this->authorizeAdmin();

        return view('livewire.backoffice.account.edit-profile', [
            'admin' => $admin,
        ]);
    }
}
