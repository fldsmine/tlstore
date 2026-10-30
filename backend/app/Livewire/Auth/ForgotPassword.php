<?php

namespace App\Livewire\Auth;

use App\Enums\OtpType;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Forgot Password')]
class ForgotPassword extends Component
{
    public string $email = '';

    public function sendReset(OtpService $otp): void
    {
        $this->validate(['email' => ['required', 'email']]);

        $key = 'otp-reset-send:'.Str::lower($this->email).'|'.request()->ip();

        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 600);

            $user = User::query()->where('email', $this->email)->first();

            if ($user) {
                $otp->send(null, $this->email, OtpType::PASSWORD_RESET);
            }
        }

        session(['password_reset_email' => $this->email]);
        session()->flash('status', 'If that email exists, we sent a reset code.');

        $this->redirect(route('auth.new-password'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
