<?php

namespace App\Livewire\Dashboard\Account;

use App\Livewire\Concerns\VerifiesTransactionPin;
use App\Models\DebitCard as DebitCardModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Debit Card')]
class DebitCard extends Component
{
    use VerifiesTransactionPin;

    public bool $requesting = false;

    public string $requestStep = 'details';

    public string $holderName = '';

    public string $pin = '';

    public string $pinConfirm = '';

    public string $requestTxnPin = '';

    public bool $viewing = false;

    public string $viewPin = '';

    public bool $revealed = false;

    public bool $resettingOpen = false;

    public string $resetStep = 'details';

    public string $newPin = '';

    public string $newPinConfirm = '';

    public string $resetTxnPin = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->holderName = trim($user->first_name.' '.$user->last_name) ?: $user->username;
    }

    public function startRequest(): void
    {
        if (auth()->user()->debitCard) {
            return;
        }

        $this->reset(['requestStep', 'pin', 'pinConfirm', 'requestTxnPin']);
        $this->requestStep = 'details';
        $this->requesting = true;
        $this->resetValidation();
    }

    public function closeRequest(): void
    {
        $this->reset(['requesting', 'requestStep', 'pin', 'pinConfirm', 'requestTxnPin']);
        $this->resetValidation();
    }

    public function requestNext(): void
    {
        $this->validate([
            'holderName' => ['required', 'string', 'max:60'],
            'pin' => ['required', 'digits:4'],
            'pinConfirm' => ['required', 'same:pin'],
        ], [
            'holderName.required' => 'Confirm the name for your card.',
            'pin.required' => 'Choose a 4-digit card PIN.',
            'pin.digits' => 'Your card PIN must be 4 digits.',
            'pinConfirm.same' => 'The PINs do not match.',
        ]);

        $this->requestStep = 'authorize';
        $this->resetValidation();
    }

    public function createCard(): void
    {
        $user = auth()->user();

        if ($user->debitCard) {
            $this->closeRequest();

            return;
        }

        $this->validate([
            'holderName' => ['required', 'string', 'max:60'],
            'pin' => ['required', 'digits:4'],
            'pinConfirm' => ['required', 'same:pin'],
            'requestTxnPin' => ['required', 'digits:4'],
        ], [
            'requestTxnPin.required' => 'Enter your transaction PIN to confirm.',
        ]);

        if (! $this->verifyTransactionPin($this->requestTxnPin, 'requestTxnPin')) {
            $this->reset('requestTxnPin');

            return;
        }

        $now = now();

        DebitCardModel::create([
            'user_id' => $user->id,
            'brand' => 'Verve',
            'holder_name' => strtoupper($this->holderName),
            'card_number' => DebitCardModel::generateNumber(),
            'cvv' => DebitCardModel::generateCvv(),
            'expiry_month' => (int) $now->format('n'),
            'expiry_year' => (int) $now->copy()->addYears(3)->format('Y'),
            'card_pin' => $this->pin,
            'status' => 'active',
        ]);

        $this->closeRequest();
        $this->dispatch('toast', message: 'Your virtual card is ready to use.', type: 'success');
    }

    public function startView(): void
    {
        $this->reset(['viewPin', 'revealed']);
        $this->viewing = true;
        $this->resetValidation();
    }

    public function closeView(): void
    {
        $this->reset(['viewing', 'viewPin', 'revealed']);
        $this->resetValidation();
    }

    public function revealCard(): void
    {
        $this->validate(['viewPin' => ['required', 'digits:4']], [
            'viewPin.required' => 'Enter your card PIN.',
            'viewPin.digits' => 'Your card PIN must be 4 digits.',
        ]);

        $key = 'card-pin:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('viewPin', 'Too many incorrect attempts. Try again in '.RateLimiter::availableIn($key).' seconds.');

            return;
        }

        $card = auth()->user()->debitCard;

        if (! $card || ! Hash::check($this->viewPin, $card->card_pin)) {
            RateLimiter::hit($key, 300);
            $this->reset('viewPin');
            $this->addError('viewPin', 'Incorrect card PIN.');

            return;
        }

        RateLimiter::clear($key);
        $this->revealed = true;
        $this->viewPin = '';
    }

    public function startReset(): void
    {
        $this->reset(['resetStep', 'newPin', 'newPinConfirm', 'resetTxnPin']);
        $this->resetStep = 'details';
        $this->resettingOpen = true;
        $this->resetValidation();
    }

    public function closeReset(): void
    {
        $this->reset(['resettingOpen', 'resetStep', 'newPin', 'newPinConfirm', 'resetTxnPin']);
        $this->resetValidation();
    }

    public function resetNext(): void
    {
        $this->validate([
            'newPin' => ['required', 'digits:4'],
            'newPinConfirm' => ['required', 'same:newPin'],
        ], [
            'newPin.required' => 'Choose a new 4-digit PIN.',
            'newPin.digits' => 'Your PIN must be 4 digits.',
            'newPinConfirm.same' => 'The PINs do not match.',
        ]);

        $this->resetStep = 'authorize';
        $this->resetValidation();
    }

    public function submitReset(): void
    {
        $user = auth()->user();
        $card = $user->debitCard;

        if (! $card) {
            return;
        }

        $this->validate([
            'newPin' => ['required', 'digits:4'],
            'newPinConfirm' => ['required', 'same:newPin'],
            'resetTxnPin' => ['required', 'digits:4'],
        ], [
            'newPin.required' => 'Choose a new 4-digit PIN.',
            'newPin.digits' => 'Your PIN must be 4 digits.',
            'newPinConfirm.same' => 'The PINs do not match.',
            'resetTxnPin.required' => 'Enter your transaction PIN to confirm.',
        ]);

        if (! $this->verifyTransactionPin($this->resetTxnPin, 'resetTxnPin')) {
            $this->reset('resetTxnPin');

            return;
        }

        $card->update(['card_pin' => $this->newPin]);

        $this->closeReset();
        $this->dispatch('toast', message: 'Your card PIN has been reset.', type: 'success');
    }

    public function render(): View
    {

        $card = DebitCardModel::query()->where('user_id', auth()->id())->first();

        return view('livewire.dashboard.account.debit-card', [
            'card' => $card,
            'holder' => $card?->holder_name ?: $this->holderName,
        ]);
    }
}
