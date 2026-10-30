<?php

namespace App\Livewire\Dashboard\Withdrawals;

use App\Livewire\Concerns\VerifiesTransactionPin;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\WithdrawalService;
use App\Support\DisplayCurrency;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Withdraw')]
class Withdraw extends Component
{
    use VerifiesTransactionPin;

    public string $wallet = 'shareholder';

    public ?string $amount = null;

    public string $pin = '';

    public string $withdrawalCode = '';

    /** True when the entered code is valid but not yet activated — the view then links
     *  to the Special Coupon page so the user can activate it. */
    public bool $codeNeedsActivation = false;

    public bool $confirming = false;

    public function mount(): void
    {
        $requested = (string) request()->query('wallet', '');
        $applicable = $this->service()->applicableWalletKeys($this->user());

        $this->wallet = in_array($requested, $applicable, true)
            ? $requested
            : (string) ($applicable[0] ?? 'shareholder');
    }

    protected function service(): WithdrawalService
    {
        return app(WithdrawalService::class);
    }

    protected function applicableWalletKeys(): array
    {
        return $this->service()->applicableWalletKeys($this->user());
    }

    protected function withdrawableWalletKeys(): array
    {
        return $this->service()->withdrawableWalletKeys($this->user());
    }

    protected function walletIsOpen(): bool
    {
        return $this->service()->isOpen($this->user(), $this->wallet);
    }

    public function updatedWallet(string $value): void
    {

        if (! in_array($value, $this->applicableWalletKeys(), true)) {
            $this->wallet = $this->applicableWalletKeys()[0] ?? 'shareholder';
        }

        // A code entered for one wallet must not linger when switching wallets.
        $this->reset('withdrawalCode');
        $this->codeNeedsActivation = false;
        $this->resetValidation();
    }

    public function updatedWithdrawalCode(): void
    {
        $this->codeNeedsActivation = false;
        $this->resetValidation('withdrawalCode');
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

    public function fillMax(): void
    {
        $this->amount = (string) (int) floor($this->inEntryCurrency($this->available()));
        $this->resetValidation('amount');
    }

    protected function requiresWithdrawalCode(): bool
    {
        return $this->service()->walletRequiresAuthorizationCode($this->wallet);
    }

    public function review(): void
    {
        $this->guardWithdrawable();
        $this->validate($this->amountRules(), $this->amountMessages());

        // The authorization code lives on this page: validate it server-side (without
        // consuming it) and only then open the confirm sheet. Consumption happens
        // atomically inside WithdrawalService::request() when the withdrawal is confirmed.
        $this->codeNeedsActivation = false;

        if ($this->requiresWithdrawalCode()) {
            $this->validate($this->withdrawalCodeRules(), $this->withdrawalCodeMessages());

            try {
                $this->service()->validateAuthorizationCode($this->user(), $this->withdrawalCode);
            } catch (ValidationException $e) {
                $message = $e->validator->errors()->first('withdrawal_code') ?? $e->validator->errors()->first();
                $this->addError('withdrawalCode', $message);
                $this->codeNeedsActivation = $message === WithdrawalService::CODE_NOT_LINKED_MESSAGE;

                return;
            }
        }

        $this->confirming = true;
    }

    public function closeConfirm(): void
    {
        $this->confirming = false;
        $this->reset('pin');
        $this->codeNeedsActivation = false;
        $this->resetValidation('pin');
    }

    public function withdraw(): void
    {
        $this->guardWithdrawable();

        $rules = $this->amountRules();
        $messages = $this->amountMessages();

        if ($this->requiresWithdrawalCode()) {
            $rules = array_merge($rules, $this->withdrawalCodeRules());
            $messages = array_merge($messages, $this->withdrawalCodeMessages());
        }

        $rules['pin'] = ['required', 'digits:4'];

        $this->validate($rules, $messages);

        $user = $this->user();

        if (! $this->verifyTransactionPin($this->pin)) {
            $this->reset('pin');

            return;
        }

        try {
            $withdrawal = $this->service()->request(
                $user,
                $this->wallet,
                $this->usdAmount(),
                $this->withdrawalCode
            );
        } catch (ValidationException $e) {
            // Service-level failures (code consumed/expired in the meantime, balance changed).
            // Surface them on the main-page fields — the code input no longer lives in the sheet.
            $this->reset('pin');
            $this->confirming = false;

            foreach ($e->errors() as $key => $errorMessages) {
                $this->addError($key === 'withdrawal_code' ? 'withdrawalCode' : $key, $errorMessages[0]);
            }

            $codeError = $e->errors()['withdrawal_code'][0] ?? null;
            $this->codeNeedsActivation = $codeError === WithdrawalService::CODE_NOT_LINKED_MESSAGE;

            return;
        }

        $this->reset('pin', 'withdrawalCode');
        $this->codeNeedsActivation = false;

        $this->redirectRoute('withdrawal.success', ['ref' => $withdrawal->reference], navigate: true);
    }

    protected function withdrawalCodeRules(): array
    {
        return [
            'withdrawalCode' => ['required', 'string', 'min:6'],
        ];
    }

    protected function withdrawalCodeMessages(): array
    {
        return [
            'withdrawalCode.required' => 'A withdrawal authorization code is required for N-Cloud withdrawals.',
            'withdrawalCode.min' => 'The withdrawal authorization code must be at least 6 characters.',
        ];
    }

    protected function amountRules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'gt:0',

                'min:'.$this->inEntryCurrency($this->minAmount()),
                'max:'.$this->inEntryCurrency($this->available()),
            ],
        ];
    }

    protected function amountMessages(): array
    {
        return [
            'amount.required' => 'Enter an amount to withdraw.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.gt' => 'The amount must be greater than zero.',
            'amount.min' => 'The minimum withdrawal is '.money($this->minAmount()).'.',
            'amount.max' => 'The amount exceeds your available balance.',
            'pin.required' => 'Enter your 4-digit transaction PIN.',
            'pin.digits' => 'Your PIN must be 4 digits.',
        ];
    }

    protected function guardWithdrawable(): void
    {
        $this->service()->guardWithdrawable($this->user(), $this->wallet, $this->usdAmount());
    }

    protected function hasPendingWithdrawal(): bool
    {
        return $this->service()->hasPendingWithdrawal($this->user(), $this->wallet);
    }

    protected function available(): float
    {
        return $this->user()->walletBalance($this->wallet);
    }

    protected function entryIsLocal(): bool
    {
        return DisplayCurrency::isLocal($this->user());
    }

    protected function usdAmount(): float
    {
        $typed = max(0.0, (float) ($this->amount ?: 0));

        return $this->entryIsLocal()
            ? round(app(CurrencyService::class)->toUsd($typed, $this->user()->country), 2)
            : round($typed, 2);
    }

    protected function inEntryCurrency(float $usd): float
    {
        return $this->entryIsLocal()
            ? floor(DisplayCurrency::convert($usd, $this->user()) * 100) / 100
            : round($usd, 2);
    }

    protected function minAmount(): float
    {
        return $this->service()->minAmount($this->wallet);
    }

    protected function flatFee(): float
    {
        return $this->service()->flatFee($this->wallet);
    }

    protected function maxAmount(): float
    {
        return $this->service()->maxAmount($this->wallet);
    }

    public function render(): View
    {
        $service = $this->service();
        $user = $this->user();
        $open = $this->withdrawableWalletKeys();

        $wallets = collect($this->applicableWalletKeys())
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => User::WALLET_LABELS[$key],
                'balance' => $user->walletBalance($key),
                'open' => in_array($key, $open, true),
            ])
            ->values();

        $amount = $this->usdAmount();
        [$fee, $net] = $service->payoutFor($this->wallet, $amount);

        $requiresWithdrawalCode = $this->requiresWithdrawalCode();

        return view('livewire.dashboard.withdrawals.withdraw', [
            'wallets' => $wallets,
            'pinResetPrice' => RegionPricing::pinResetCustomer(RegionPricing::forUser(auth()->user())),
            'walletLabel' => User::WALLET_LABELS[$this->wallet] ?? 'Wallet',
            'walletOpen' => $this->walletIsOpen(),
            'anyWalletOpen' => count($open) > 0,
            'pendingWithdrawal' => $this->hasPendingWithdrawal(),
            'available' => $this->available(),
            'minAmount' => $this->minAmount(),
            'flatFee' => $this->flatFee(),
            'fee' => $fee,
            'net' => $net,
            'smsBonusRate' => $service->smsBonusRate($this->wallet),

            'amountUsd' => $amount,
            'entrySymbol' => DisplayCurrency::symbol($user),
            'entryCode' => DisplayCurrency::code($user),

            'localCurrency' => app(CurrencyService::class)->code($user->country),
            'bankAccount' => $user->bankAccount,
            'isActivated' => (bool) $user->package_id,
            'userBlocked' => (bool) $user->withdrawals_disabled,
            'requiresWithdrawalCode' => $requiresWithdrawalCode,
        ]);
    }

    protected function user(): User
    {
        return auth()->user()->loadMissing('bankAccount.bank');
    }
}
