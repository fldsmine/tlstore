<?php

namespace App\Livewire\Dashboard;

use App\Enums\ChargePurpose;
use App\Models\Package;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\TokenRedemptionService;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Activate extends Component
{
    public bool $open = false;

    public ?string $packageId = null;

    public string $token = '';

    public bool $done = false;

    public ?string $activatedId = null;

    public bool $wasUpgrade = false;

    public function mount(): void
    {

        if (request()->boolean('activate') && ! auth()->user()?->package_id) {
            $this->open = true;
        }
    }

    #[On('open-activation')]
    public function show(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->reset(['token', 'packageId', 'activatedId', 'wasUpgrade']);
        $this->resetErrorBag();
    }

    public function activate(TokenRedemptionService $redemption): void
    {
        $user = auth()->user()->loadMissing('package');
        $isUpgrade = (bool) $user->package;

        $this->validate([
            'packageId' => ['required', 'string', 'exists:packages,id'],
            'token' => ['required', 'string', 'min:6', 'max:32'],
        ], [
            'packageId.required' => $isUpgrade ? 'Choose the package you want to upgrade to.' : 'Choose the package you want to activate.',
            'token.required' => 'Enter the '.($isUpgrade ? 'upgrade' : 'activation').' token you bought from an agent.',
        ]);

        // Upgrades redeem a dedicated UPGRADE coupon; first-time activation redeems an ACTIVATION one.
        $package = $isUpgrade
            ? $redemption->upgrade($user, (string) $this->packageId, $this->token, 'packageId', 'token')
            : $redemption->activate($user, (string) $this->packageId, $this->token, 'packageId', 'token');

        $this->activatedId = $package->id;
        $this->wasUpgrade = $isUpgrade;
        $this->done = true;
    }

    public function render(): View
    {
        $current = auth()->user()->loadMissing('package')->package;

        return view('livewire.dashboard.activate', [

            'packages' => Package::query()->active()->orderBy('tier_level')->get(),
            'currentPackage' => $current,
            // Direct payment is only offered while the configured package channel is usable.
            'directPay' => app(PaymentGatewayManager::class)->availableFor(ChargePurpose::PACKAGE),

            'isUpgrade' => $this->done ? $this->wasUpgrade : (bool) $current,
            'activatedPackage' => $this->activatedId ? Package::find($this->activatedId) : null,

            'region' => RegionPricing::forUser(auth()->user()),
        ]);
    }
}
