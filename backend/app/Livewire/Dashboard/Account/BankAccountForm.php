<?php

namespace App\Livewire\Dashboard\Account;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Services\BankAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Manages the user's single bank account — where their withdrawals are paid. Vendors may change it
 * anytime; everyone else sets it once (support changes it thereafter). Nigeria verifies the account
 * name instantly; foreign accounts are a self-confirmed mobile-money wallet.
 */
class BankAccountForm extends Component
{
    public string $bankId = '';

    public string $accountNumber = '';

    public string $accountName = '';

    public bool $verified = false;

    public bool $verifying = false;

    public string $verifyError = '';

    public bool $added = false;

    public bool $confirmed = false;

    public bool $editing = false;

    public function isNigerian(): bool
    {
        return auth()->user()->isNigerian();
    }

    protected function account(): ?BankAccount
    {
        return auth()->user()->bankAccount;
    }

    public function updatedBankId(): void
    {
        $this->resetVerification();

        if ($this->isNigerian()) {
            $this->maybeVerify();
        }
    }

    public function updatedAccountNumber(): void
    {
        $this->accountNumber = $this->isNigerian()
            ? (string) preg_replace('/\D/', '', (string) $this->accountNumber)
            : (string) preg_replace('/[^0-9+]/', '', (string) $this->accountNumber);

        $this->resetVerification();

        if ($this->isNigerian()) {
            $this->maybeVerify();
        }
    }

    private function maybeVerify(): void
    {
        if ($this->bankId && strlen($this->accountNumber) === 10) {
            $this->verifyAccount();
        }
    }

    private function resetVerification(): void
    {
        $this->verified = false;
        $this->verifyError = '';
        $this->added = false;

        if ($this->isNigerian()) {
            $this->accountName = '';
        }
    }

    public function verifyAccount(): void
    {
        if (! $this->isNigerian()) {
            return;
        }

        $this->validate([
            'bankId' => ['required', $this->countryBankRule()],
            'accountNumber' => ['required', 'digits:10'],
        ]);

        $this->verifying = true;
        $this->verified = false;
        $this->accountName = '';
        $this->verifyError = '';

        $result = app(BankAccountService::class)->verifyNigerianAccount($this->bankId, $this->accountNumber);

        if ($result['ok']) {
            $this->accountName = $result['name'];
            $this->verified = true;
        } else {
            $this->verifyError = $result['error'] ?? 'Could not verify this account. Check the details and try again.';
        }

        $this->verifying = false;
    }

    /** Reveal the edit form — vendors only (everyone else sets the account once via support). */
    public function edit(): void
    {
        abort_unless(auth()->user()->is_agent, 403);

        $this->editing = true;
        $this->reset(['bankId', 'accountNumber', 'accountName', 'verified', 'verifyError', 'confirmed']);
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->reset(['bankId', 'accountNumber', 'accountName', 'verified', 'verifyError', 'confirmed']);
        $this->resetValidation();
    }

    public function save(): void
    {
        // Vendors may change their account anytime; everyone else sets it once (support changes it).
        if ($this->account() && ! auth()->user()->is_agent) {
            $this->addError('accountNumber', 'You already have a payout account. Contact support to change it.');

            return;
        }

        $this->isNigerian() ? $this->saveNigerianBank() : $this->saveForeignWallet();
    }

    private function saveNigerianBank(): void
    {
        $this->validate([
            'bankId' => ['required', $this->countryBankRule()],
            'accountNumber' => ['required', 'digits:10'],
        ]);

        try {
            app(BankAccountService::class)->saveNigerian(auth()->user(), $this->bankId, $this->accountNumber);
        } catch (ValidationException $e) {
            $this->verified = false;
            $this->accountName = '';
            $this->verifyError = (string) collect($e->errors())->flatten()->first();
            $this->addError('accountNumber', $this->verifyError);

            return;
        }

        $this->markSaved();
    }

    private function saveForeignWallet(): void
    {
        $this->validate([
            'bankId' => ['required', $this->countryBankRule()],
            'accountNumber' => ['required', 'string', 'min:6', 'max:20', 'regex:/^\+?[0-9 ]{6,20}$/'],
            'accountName' => ['required', 'string', 'max:120'],
            'confirmed' => ['accepted'],
        ], [
            'accountNumber.required' => 'Enter your mobile-money number.',
            'accountNumber.regex' => 'Enter a valid mobile-money number.',
            'accountName.required' => 'Enter the account name exactly as registered on the wallet.',
            'confirmed.accepted' => 'Please confirm your mobile-money details are correct.',
        ]);

        app(BankAccountService::class)->saveForeign(auth()->user(), $this->bankId, $this->accountNumber, trim($this->accountName));

        $this->markSaved();
    }

    private function markSaved(): void
    {
        $this->reset(['bankId', 'accountNumber', 'accountName', 'verified', 'verifyError', 'confirmed']);
        $this->editing = false;
        $this->added = true;
    }

    private function countryBankRule(): Exists
    {
        return app(BankAccountService::class)->countryBankRule(auth()->user());
    }

    public function render(): View
    {
        return view('livewire.dashboard.account.bank-account-form', [
            'banks' => Bank::query()->active()->forCountry(auth()->user()->country)->orderBy('name')->get(),
            'account' => $this->account()?->load('bank'),
            'isNigerian' => $this->isNigerian(),
            'isVendor' => auth()->user()->is_agent,
            'copy' => [
                'saved_hint' => 'This is where your withdrawals are paid.',
                'add_hint_ng' => 'Add the Nigerian bank account where your withdrawals will be paid — we verify the account name instantly.',
                'add_hint_foreign' => 'Add the mobile-money wallet where your withdrawals will be paid. Make sure the provider, number and name match your registered wallet.',
                'edit_label' => 'Change payout account',
            ],
        ]);
    }
}
