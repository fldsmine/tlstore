<?php

namespace App\Services;

use App\Contracts\BankVerifier;
use App\Enums\BankAccountStatus;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Payout-account rules shared by the web (Livewire BankAccountForm) and API.
 * Vendors may change their account anytime; everyone else sets it once
 * (support changes it thereafter). Nigeria verifies the account name
 * instantly; foreign accounts are self-confirmed mobile-money wallets.
 */
class BankAccountService
{
    /** @return array{ok: bool, name?: string, error?: string} */
    public function verifyNigerianAccount(string $bankId, string $accountNumber): array
    {
        $bank = Bank::query()->whereKey($bankId)->first();

        if (! $bank) {
            return ['ok' => false, 'error' => 'Choose a valid bank.'];
        }

        $result = app(BankVerifier::class)->resolveBankAccount($accountNumber, $bank->code);
        $name = $result['data']['account_name'] ?? null;

        if (($result['status'] ?? '') !== 'success' || empty($name)) {
            return ['ok' => false, 'error' => $result['message'] ?? 'Could not verify this account. Check the details and try again.'];
        }

        return ['ok' => true, 'name' => (string) $name];
    }

    /** Set or replace the payout account, enforcing the vendor/non-vendor rules. */
    public function save(User $user, string $bankId, string $accountNumber, string $accountName, BankAccountStatus $status): BankAccount
    {
        $existing = $user->bankAccount()->first();

        if ($existing && ! $user->is_agent) {
            throw ValidationException::withMessages([
                'account_number' => 'You already have a payout account. Contact support to change it.',
            ]);
        }

        $this->assertBankForCountry($user, $bankId);

        return $user->bankAccounts()->updateOrCreate([], [
            'bank_id' => $bankId,
            'account_number' => $accountNumber,
            'account_name' => $accountName,
            'status' => $status,
        ]);
    }

    /** Nigerian save flow: bank must exist for the country; the name is resolved live. */
    public function saveNigerian(User $user, string $bankId, string $accountNumber): BankAccount
    {
        $verified = $this->verifyNigerianAccount($bankId, $accountNumber);

        if (! $verified['ok']) {
            throw ValidationException::withMessages(['account_number' => $verified['error'] ?? 'Could not verify this account.']);
        }

        return $this->save($user, $bankId, $accountNumber, (string) $verified['name'], BankAccountStatus::VERIFIED);
    }

    /** Foreign save flow: self-confirmed mobile-money wallet. */
    public function saveForeign(User $user, string $bankId, string $accountNumber, string $accountName): BankAccount
    {
        return $this->save($user, $bankId, $accountNumber, $accountName, BankAccountStatus::PENDING);
    }

    public function countryBankRule(User $user): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('banks', 'id')
            ->where('country', strtoupper((string) $user->country))
            ->where('is_active', true);
    }

    private function assertBankForCountry(User $user, string $bankId): void
    {
        $bank = Bank::query()
            ->whereKey($bankId)
            ->where('country', strtoupper((string) $user->country))
            ->where('is_active', true)
            ->first();

        if (! $bank) {
            throw ValidationException::withMessages(['bank_id' => 'Choose a valid bank for your country.']);
        }
    }
}
