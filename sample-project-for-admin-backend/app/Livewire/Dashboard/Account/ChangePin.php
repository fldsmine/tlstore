<?php

namespace App\Livewire\Dashboard\Account;

use App\Livewire\Concerns\VerifiesTransactionPin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Change PIN')]
class ChangePin extends Component
{
    use VerifiesTransactionPin;

    public string $currentPin = '';

    public string $newPin = '';

    public string $confirmPin = '';

    public bool $done = false;

    public function proceed(): void
    {
        $this->validate([
            'currentPin' => ['required', 'digits:4'],
            'newPin' => ['required', 'digits:4', 'different:currentPin'],
            'confirmPin' => ['required', 'same:newPin'],
        ], [
            'newPin.different' => 'Your new PIN must be different from your current PIN.',
            'confirmPin.same' => 'The PINs do not match.',
        ]);

        if (! $this->verifyTransactionPin($this->currentPin, 'currentPin')) {
            return;
        }

        auth()->user()->update(['transaction_pin' => $this->newPin]);

        $this->reset(['currentPin', 'newPin', 'confirmPin']);
        $this->done = true;
    }

    public function render(): View
    {
        return view('livewire.dashboard.account.change-pin');
    }
}
