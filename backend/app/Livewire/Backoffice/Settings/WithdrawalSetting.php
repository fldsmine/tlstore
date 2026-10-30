<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Withdrawal Settings — Backoffice')]
class WithdrawalSetting extends Component
{
    use AuthorizesAdminActions;

    public array $wallets = [];

    public string $bankVerificationProvider = 'paystack';

    public string $payoutNarration = '';

    public function mount(): void
    {
        foreach (User::SPENDABLE_WALLETS as $key) {
            $this->wallets[$key] = [
                'enabled' => (bool) config("settings.withdrawal.wallets.{$key}.enabled"),
                'min' => (string) config("settings.withdrawal.wallets.{$key}.min"),
                'max' => (string) config("settings.withdrawal.wallets.{$key}.max"),
                'fee' => (string) config("settings.withdrawal.wallets.{$key}.fee"),
                'auto_approve' => (bool) config("settings.withdrawal.wallets.{$key}.auto_approve"),
                'deduct_at' => (string) config("settings.withdrawal.wallets.{$key}.deduct_at"),
                'country_access' => (string) config("settings.withdrawal.wallets.{$key}.country_access", 'all'),
            ];
        }

        $this->bankVerificationProvider = (string) config('settings.payment.bank_verification_provider');
        $this->payoutNarration = (string) config('settings.payment.narration');
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $validator = Validator::make(
            ['wallets' => $this->wallets, 'bankVerificationProvider' => $this->bankVerificationProvider, 'payoutNarration' => $this->payoutNarration],
            [
                'wallets.*.min' => ['required', 'numeric', 'min:0'],
                'wallets.*.max' => ['required', 'numeric', 'min:0'],
                'wallets.*.fee' => ['required', 'numeric', 'min:0'],
                'wallets.*.auto_approve' => ['boolean'],
                'wallets.*.deduct_at' => ['required', 'in:request,approval'],
                'wallets.*.country_access' => ['required', 'in:all,nigerian,non_nigerian'],
                'bankVerificationProvider' => ['required', 'in:paystack,flutterwave'],
                'payoutNarration' => ['nullable', 'string', 'max:120'],
            ],
            [
                'wallets.*.min.required' => 'Enter a minimum amount.',
                'wallets.*.min.numeric' => 'The minimum must be a number.',
                'wallets.*.min.min' => 'The minimum cannot be negative.',
                'wallets.*.max.required' => 'Enter a maximum (0 for no cap).',
                'wallets.*.max.numeric' => 'The maximum must be a number.',
                'wallets.*.max.min' => 'The maximum cannot be negative.',
                'wallets.*.fee.required' => 'Enter a fee (0 for none).',
                'wallets.*.fee.numeric' => 'The fee must be a number.',
                'wallets.*.fee.min' => 'The fee cannot be negative.',
                'wallets.*.deduct_at.required' => 'Choose when the wallet is debited.',
            ]
        );

        $validator->after(function ($v): void {
            foreach ($this->wallets as $key => $config) {
                if (($config['auto_approve'] ?? false) && ($config['deduct_at'] ?? 'request') !== 'request') {
                    $v->errors()->add("wallets.{$key}.deduct_at", 'Auto-approve requires deduction at request time.');
                }
            }
        });

        $validator->validate();

        $payload = [
            'bank_verification_provider' => $this->bankVerificationProvider,
            'payout_narration' => $this->payoutNarration,
        ];

        foreach ($this->wallets as $key => $config) {
            $autoApprove = (bool) ($config['auto_approve'] ?? false);
            $payload["enable_{$key}_withdrawal"] = $config['enabled'] ?? true;
            $payload["{$key}_withdrawal_min"] = $config['min'] ?? 0;
            $payload["{$key}_withdrawal_max"] = $config['max'] ?? 0;
            $payload["{$key}_withdrawal_fee"] = $config['fee'] ?? 0;
            $payload["enable_{$key}_withdrawal_auto_approve"] = $autoApprove;

            $payload["{$key}_withdrawal_deduct_at"] = $autoApprove ? 'request' : ($config['deduct_at'] ?? 'request');
            $payload["{$key}_withdrawal_country_access"] = $config['country_access'] ?? 'all';
        }

        update_setting($payload);

        $this->dispatch('toast', message: 'Withdrawal settings saved successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.settings.withdrawal-setting', [
            'walletLabels' => Arr::only(User::WALLET_LABELS, User::SPENDABLE_WALLETS),
        ]);
    }
}
