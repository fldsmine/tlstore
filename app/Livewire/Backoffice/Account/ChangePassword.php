<?php

namespace App\Livewire\Backoffice\Account;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Change password')]
class ChangePassword extends Component
{
    use AuthorizesAdminActions;

    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function save(): void
    {
        $admin = $this->authorizeAdmin();

        $data = $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'confirmed', Password::min(12)],
        ]);

        if (! Hash::check($data['currentPassword'], $admin->password ?? '')) {
            $this->addError('currentPassword', 'The current password is incorrect.');

            return;
        }

        // Mirrors the user-side change: new hash, remember token rotated so other
        // sessions must re-authenticate. Existing admin Sanctum tokens are revoked.
        $admin->forceFill([
            'password' => $data['newPassword'],
            'remember_token' => Str::random(60),
        ])->save();
        $admin->tokens()->delete();

        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => 'admin.password_changed',
            'entity_type' => 'admin',
            'entity_id' => (string) $admin->id,
            'after_values' => ['tokens_revoked' => true],
            'created_at' => now(),
        ]);

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        $this->dispatch('toast', message: 'Password updated. Signed-in API tokens were revoked.', type: 'success');
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        return view('livewire.backoffice.account.change-password');
    }
}
