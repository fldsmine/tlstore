<?php

namespace App\Livewire\Dashboard\Account;

use App\Services\TokenRedemptionService;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Reset PIN')]
class ResetPin extends Component
{
    public string $token = '';

    public string $newPin = '';

    public string $confirmPin = '';

    public bool $done = false;

    public function mount(): void
    {
        // Prefill a code carried over from the buy-chat's "Reset PIN" button.
        $this->token = (string) session('carry_code', $this->token);
    }

    public function proceed(TokenRedemptionService $redemption): void
    {
        $this->validate([
            'token' => ['required', 'string', 'min:6', 'max:32'],
            'newPin' => ['required', 'digits:4'],
            'confirmPin' => ['required', 'same:newPin'],
        ], [
            'token.required' => 'Enter the PIN-change token you bought from an agent.',
            'newPin.digits' => 'Your PIN must be exactly 4 digits.',
            'confirmPin.same' => 'The PINs do not match.',
        ]);

        $redemption->resetPin(auth()->user(), $this->token, $this->newPin, 'token');

        $this->reset(['token', 'newPin', 'confirmPin']);
        $this->done = true;
    }

    public function render(): View
    {
        return view('livewire.dashboard.account.reset-pin', [
            // Vendors mint the token in the user's own region, so quote that region's price.
            'tokenPrice' => RegionPricing::pinResetCustomer(RegionPricing::forUser(auth()->user())),
        ]);
    }
}
