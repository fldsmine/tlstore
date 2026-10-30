<?php

namespace App\Livewire\Auth;

use App\Models\Country;
use App\Services\Auth\RegistrationService;
use App\Services\OtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Create Account')]
class Register extends Component
{
    public string $fullName = '';

    public string $username = '';

    public string $email = '';

    public string $phone = '';

    public string $country = '';

    public string $referralCode = '';

    public string $password = '';

    public bool $agreeTerms = false;

    public bool $referralLocked = false;

    public function mount(): void
    {
        abort_unless(self::registrationOpen(), 404);

        $ref = trim((string) request()->query('ref', ''));

        if ($ref !== '') {
            $this->referralCode = $ref;
            $this->referralLocked = true;
        }
    }

    private static function registrationOpen(): bool
    {
        return RegistrationService::isOpen();
    }

    public function register(RegistrationService $registration): void
    {
        $this->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'country' => ['required', 'string', Rule::exists('countries', 'code')->where('is_active', true)],
            'referralCode' => ['nullable', 'string', 'exists:users,username'],
            'password' => ['required', 'string', Password::defaults()],
            'agreeTerms' => ['accepted'],
        ], [
            'agreeTerms.accepted' => 'Please agree to the Terms & Conditions to continue.',
        ]);

        [$first, $last] = $this->splitName($this->fullName);

        $user = $registration->register([
            'first_name' => $first,
            'last_name' => $last,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'password' => $this->password,
            'referral_code' => $this->referralCode,
        ]);

        Auth::login($user);

        session()->flash('toast', ['type' => 'success', 'message' => 'Registration successful, Activate your eSIM']);

        if ($registration->dispatchVerification($user)) {
            $this->redirect(route('auth.verify-email'), navigate: true);

            return;
        }

        $this->redirect(route('dashboard'), navigate: true);
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    public function render(): View
    {
        return view('livewire.auth.register', [
            'countries' => Country::query()->active()->orderBy('name')->get(),
        ]);
    }
}
