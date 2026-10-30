<?php

namespace App\Services\Payments;

use App\Contracts\BankVerifier;

class MockVerifier implements BankVerifier
{
    public function resolveBankAccount(string $accountNumber, string $bankCode): array
    {
        $name = strtoupper(trim((string) (auth()->user()?->full_name ?? '')));

        return [
            'status' => 'success',
            'data' => [
                'provider' => 'mock',
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'account_name' => $name !== '' ? $name : 'NEXTEL TEST ACCOUNT',
            ],
        ];
    }
}
