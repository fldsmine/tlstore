<?php

namespace App\Livewire\Dashboard\Agent;

use App\Livewire\Concerns\VerifiesTransactionPin;
use App\Models\User;
use App\Services\AgentTransferService;
use App\Services\CurrencyService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Transfer Funds')]
class Transfer extends Component
{
    use VerifiesTransactionPin;

    public string $recipientUsername = '';

    public ?string $amount = null;

    public string $pin = '';

    public bool $confirming = false;

    public ?string $transferNonce = null;

    public ?array $preview = null;

    public function mount(): void
    {

        if (! auth()->user()->isSpecialVendor()) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function updatedAmount(string $value): void
    {
        $clean = preg_replace('/[^0-9.]/', '', $value) ?? '';

        if (substr_count($clean, '.') > 1) {
            [$whole, $rest] = explode('.', $clean, 2);
            $clean = $whole.'.'.str_replace('.', '', $rest);
        }

        $this->amount = $clean;
        $this->resetValidation('amount');
    }

    public function review(): void
    {
        $sender = auth()->user();

        $this->validate([
            'recipientUsername' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.(float) $sender->agent_balance],
        ], [
            'recipientUsername.required' => 'Enter the vendor’s username.',
            'amount.required' => 'Enter an amount to transfer.',
            'amount.gt' => 'The amount must be greater than zero.',
            'amount.max' => 'The amount exceeds your vendor balance.',
        ]);

        $recipient = User::query()
            ->where('username', trim($this->recipientUsername))
            ->where('is_agent', true)
            ->first();

        if (! $recipient) {
            $this->addError('recipientUsername', 'No vendor found with that username.');

            return;
        }

        if ($recipient->id === $sender->id) {
            $this->addError('recipientUsername', 'You cannot transfer to yourself.');

            return;
        }

        $this->preview = [
            'username' => $recipient->username,
            'name' => $recipient->full_name,
            'avatar' => $recipient->avatar,
            'amount' => round((float) $this->amount, 2),
        ];
        $this->transferNonce = (string) Str::random(24);
        $this->confirming = true;
    }

    public function closeConfirm(): void
    {
        $this->confirming = false;
        $this->reset('pin');
        $this->resetValidation('pin');
    }

    public function transfer(AgentTransferService $service): void
    {
        $sender = auth()->user();
        abort_unless($sender->isSpecialVendor(), 403);

        $this->validate(['pin' => ['required', 'digits:4']]);

        if (! $this->verifyTransactionPin($this->pin)) {
            $this->reset('pin');

            return;
        }

        try {
            $service->transfer($sender, trim($this->recipientUsername), round((float) $this->amount, 2), (string) $this->transferNonce);
        } catch (ValidationException $e) {

            $this->confirming = false;
            $this->reset('pin');
            $this->dispatch('toast', message: $e->validator->errors()->first() ?: 'Transfer could not be completed.', type: 'error');

            return;
        }

        $this->reset(['recipientUsername', 'amount', 'pin', 'confirming', 'preview', 'transferNonce']);
        $this->dispatch('toast', message: 'Transfer completed successfully.', type: 'success');
    }

    public function render(): View
    {
        $user = auth()->user();
        $fx = app(CurrencyService::class);

        return view('livewire.dashboard.agent.transfer', [
            'balance' => (float) $user->agent_balance,
            'localBalance' => $fx->formatLocal((float) $user->agent_balance, $user->country),
            'localCurrency' => $fx->code($user->country),
        ]);
    }
}
