<?php

namespace App\Livewire\Auth;

use App\Enums\OtpType;
use App\Services\OtpService;
use App\Support\Onboarding;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Verify Email')]
class VerifyEmail extends Component
{
    public string $code = '';

    public function mount(): void
    {
        $user = Auth::user();

        if (! config('settings.app.enable_email_verification')) {
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->redirect(route(Onboarding::nextRoute($user) ?? 'dashboard'), navigate: true);

            return;
        }

        if ($user->email_verified_at) {
            $this->redirect(route(Onboarding::nextRoute($user) ?? 'dashboard'), navigate: true);
        }
    }

    public function verify(OtpService $otp): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        $user = Auth::user();

        if (! $otp->verify($user, null, OtpType::EMAIL_VERIFICATION, $this->code)) {
            $this->addError('code', 'That code is invalid or has expired.');

            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->dispatch('email-verified');
    }

    public function continueOnboarding(): void
    {
        $this->redirect(route(Onboarding::nextRoute(Auth::user()) ?? 'dashboard'));
    }

    public function resend(OtpService $otp): void
    {
        $key = 'otp-send:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('code', 'Please wait a moment before requesting another code.');

            return;
        }

        RateLimiter::hit($key, 60);
        $otp->send(Auth::user(), null, OtpType::EMAIL_VERIFICATION);

        session()->flash('status', 'A new code has been sent.');
    }

    public function render(): View
    {
        return view('livewire.auth.verify-email');
    }
}
