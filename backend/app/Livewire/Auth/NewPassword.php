<?php

namespace App\Livewire\Auth;

use App\Enums\OtpType;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('New Password')]
class NewPassword extends Component
{
    public string $email = '';

    public string $code = '';

    public string $password = '';

    public bool $emailLocked = false;

    public function mount(): void
    {
        $this->email = (string) session('password_reset_email', '');
        $this->emailLocked = $this->email !== '';
    }

    public function updatePassword(OtpService $otp): void
    {
        $this->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if (! $otp->verify(null, $this->email, OtpType::PASSWORD_RESET, $this->code)) {
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        User::query()->where('email', $this->email)->first()
            ->forceFill(['password' => $this->password])
            ->save();

        session()->forget('password_reset_email');
        session()->flash('status', 'Password updated — please sign in.');

        $this->redirect(route('auth.login'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.new-password');
    }
}
