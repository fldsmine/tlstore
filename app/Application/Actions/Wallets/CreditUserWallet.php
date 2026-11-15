<?php

namespace App\Application\Actions\Wallets;

use App\Application\Money\CurrencyConverter;
use App\Enums\WalletType;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;

final class CreditUserWallet
{
    public function __construct(private readonly CurrencyConverter $currencyConverter) {}

    public function handle(
        User $user,
        WalletType $walletType,
        int $sourceAmountMinor,
        string $sourceCurrency,
        string $entryType,
        string $sourceType,
        int $sourceId,
        string $idempotencyKey,
        string $description,
        ?\DateTimeInterface $availableAt = null,
    ): LedgerEntry {
        $existing = LedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->load('wallet');
        }

        $targetCurrency = strtoupper($user->wallet_currency ?: $user->preferred_currency ?: 'USD');
        $sourceCurrency = strtoupper($sourceCurrency);
        $quote = $this->currencyConverter->convertMinor($sourceAmountMinor, $sourceCurrency, $targetCurrency);
        $wallet = Wallet::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'wallet_type' => $walletType->value,
                'currency' => $targetCurrency,
            ],
            ['status' => 'active'],
        );

        return LedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'wallet_id' => $wallet->id,
                'entry_type' => $entryType,
                'direction' => 'credit',
                'amount_minor' => $quote['amount_minor'],
                'status' => 'posted',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'metadata' => [
                    'source_amount_minor' => $sourceAmountMinor,
                    'source_currency' => $sourceCurrency,
                    'credited_amount_minor' => $quote['amount_minor'],
                    'credited_currency' => $targetCurrency,
                    'exchange_rate' => $quote['rate'],
                ],
                'available_at' => $availableAt ?? now(),
                'posted_at' => now(),
            ],
        )->load('wallet');
    }
}
