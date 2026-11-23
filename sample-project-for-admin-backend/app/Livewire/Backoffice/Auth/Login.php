<?php

namespace App\Livewire\Backoffice\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice-guest')]
#[Title('Backoffice Sign In')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login:'.Str::lower($this->email).'|'.request()->ip();
        $ipKey = 'admin-login-ip:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            $seconds = max(RateLimiter::availableIn($key), RateLimiter::availableIn($ipKey));
            $this->addError('email', 'Too many login attempts. Try again in '.$seconds.' seconds.');

            return;
        }

        if (! Auth::guard('admin')->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($ipKey, 60);
            $this->addError('email', 'These credentials do not match our records.');

            return;
        }

        RateLimiter::clear($key);
        RateLimiter::clear($ipKey);

        request()->session()->regenerate();

        $this->redirect(route('backoffice.dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.backoffice.auth.login');
    }
}
