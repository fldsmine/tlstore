<?php

namespace App\Livewire\Auth;

use App\Enums\LoginChannel;
use App\Services\Auth\LoginService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Sign In')]
class Login extends Component
{
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(LoginService $logins): void
    {
        $this->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $ip = request()->ip();

        if ($logins->throttled($this->login, $ip)) {
            $this->addError('login', 'Too many login attempts. Try again in '.$logins->availableIn($this->login, $ip).' seconds.');

            return;
        }

        $user = $logins->attempt($this->login, $this->password);

        if (! $user) {
            $this->addError('login', 'These credentials do not match our records.');

            return;
        }

        if ($logins->isSuspended($user)) {
            $this->addError('login', 'This account has been suspended. Please contact support.');

            return;
        }

        Auth::login($user, $this->remember);

        request()->session()->regenerate();

                $logins->touchLastLogin($user, LoginChannel::WEB);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
