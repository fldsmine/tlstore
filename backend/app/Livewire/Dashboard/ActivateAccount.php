<?php

namespace App\Livewire\Dashboard;

use App\Enums\ChargePurpose;
use App\Models\Package;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\TokenRedemptionService;
use App\Support\Onboarding;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.plain')]
#[Title('Activate eSIM')]
class ActivateAccount extends Component
{
    public ?string $packageId = null;

    public string $token = '';

    public bool $done = false;

    public ?string $activatedId = null;

    public function mount()
    {
        $user = auth()->user();

        // Email verification comes before activation.
        if (config('settings.app.enable_email_verification') && ! $user->email_verified_at) {
            return redirect()->route('auth.verify-email');
        }

        // Already activated → move on to the next step.
        if ($user->package_id) {
            return redirect()->route(Onboarding::nextRoute($user) ?? 'dashboard');
        }

        // Prefill a code carried over from the buy-chat's "Activate" button.
        $this->token = (string) session('carry_code', $this->token);
    }

    public function activate(TokenRedemptionService $redemption): void
    {
        $user = auth()->user();

        // The mount() redirect keeps activated users off this page, but the action must refuse too —
        // re-activating would overwrite the package, re-allocate Call IDs and re-pay the referral chain.
        if ($user->package_id) {
            $this->addError('token', 'Your account is already activated.');

            return;
        }

        $this->validate([
            'packageId' => ['required', 'string', 'exists:packages,id'],
            'token' => ['required', 'string', 'min:6', 'max:32'],
        ], [
            'packageId.required' => 'Choose the package you want to activate.',
            'token.required' => 'Enter the activation token you bought from a distributor.',
        ]);

        $package = $redemption->activate($user, (string) $this->packageId, $this->token, 'packageId', 'token');

        $this->activatedId = $package->id;
        $this->done = true;
    }

    public function render(): View
    {
        $user = auth()->user()->loadMissing('referrer');

        return view('livewire.dashboard.activate-account', [
            'packages' => Package::query()->active()->orderBy('tier_level')->get(),
            // Direct payment is only offered while the configured package channel is usable.
            'directPay' => app(PaymentGatewayManager::class)->availableFor(ChargePurpose::PACKAGE),
            'activatedPackage' => $this->activatedId ? Package::find($this->activatedId) : null,
            'region' => RegionPricing::forUser($user),
            'telegram' => config('settings.contact.telegram_activation'),
            'upline' => $user->referrer,
        ]);
    }
}
