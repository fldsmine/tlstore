<?php

namespace App\Livewire\Auth;

use App\Support\Onboarding;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.auth')]
#[Title('Setup PIN')]
class SetupPin extends Component
{
    public string $transactionPin = '';

    public string $confirmTransactionPin = '';

    public function mount(): void
    {
        $next = Onboarding::nextRoute(Auth::user());

        // The PIN screen is the last onboarding step. If email verification is pending,
        // go there first; package activation is optional for the general dashboard.
        if ($next !== null && $next !== 'auth.setup-pin') {
            $this->redirect(route($next), navigate: true);

            return;
        }

        if ($next === null) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function setPin(): void
    {
        $this->validate([
            'transactionPin' => ['required', 'digits:4'],
            'confirmTransactionPin' => ['required', 'same:transactionPin'],
        ]);

        Auth::user()->forceFill([
            'transaction_pin' => $this->transactionPin,
        ])->save();

        $this->dispatch('pin-created');
    }

    public function continueToDashboard(): void
    {

        $this->redirect(route('dashboard'));
    }

    public function render(): View
    {
        return view('livewire.auth.setup-pin');
    }
}
