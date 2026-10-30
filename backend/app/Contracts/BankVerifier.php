<?php

namespace App\Contracts;

interface BankVerifier
{
    public function resolveBankAccount(string $accountNumber, string $bankCode): array;
}
