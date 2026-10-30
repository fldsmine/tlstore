<?php

namespace App\Services\Payments;

use App\Services\Payments\DTO\MerchantApiResult;
use App\Services\Payments\DTO\PayoutRequest;
use App\Services\Payments\DTO\PayoutResult;

/**
 * The merchant/backoffice surface a payment manager must expose so the backoffice management pages
 * (balance, balance history, send money, verify payout) can run against it unchanged.
 *
 * This is the *merchant* side of a provider — balances and payouts out of our wallet — and is
 * deliberately separate from `Gateway` (the collect/checkout side used for customer payments).
 *
 * Implementations MUST NOT throw: transport problems become `ok: false` results (or
 * {@see PayoutResult::unknown()}) so the backoffice never mistakes "we could not ask" for
 * "the provider said no" — a distinction that decides whether money may be sent again.
 */
interface MerchantWalletService
{
    /** Human name used in messages and page copy, e.g. "Korapay". */
    public function name(): string;

    /** Whether the merchant secret key is present (false ⇒ nothing can be called). */
    public function configured(): bool;

    /** Whether the configured key is a test/sandbox key. */
    public function isTestMode(): bool;

    /** @return list<string> currencies this provider can pay out in (first = default) */
    public function payoutCurrencies(): array;

    /**
     * Highest amount a single payout may carry, from THIS provider's own config. `disburse()` refuses
     * anything above it before a request is made, so the ceiling holds however the service is called.
     */
    public function maxPayoutAmount(): float;

    /** Live wallet balances, normalised to `list<array{currency,available,pending,issuing}>`. */
    public function balances(): MerchantApiResult;

    /**
     * Wallet movements, normalised across providers to pointer-based paging.
     *
     * @param  array{currency?: ?string, direction?: ?string, date_from?: ?string, date_to?: ?string, limit?: ?int, page?: ?int, starting_after?: ?string, ending_before?: ?string}  $filters
     * @return array{ok: bool, message: ?string, entries: list<array<string, mixed>>, has_more: bool, pointers: array<string, ?string>}
     */
    public function balanceHistory(array $filters = []): array;

    /**
     * Send one payout to a bank account. An `unknown` result means the provider did not answer
     * definitively — the payout may still be in flight and MUST be verified, never re-sent.
     */
    public function disburse(PayoutRequest $request): PayoutResult;

    /** Ask the provider for the authoritative status of a payout, by our reference or its id. */
    public function verifyPayout(string $reference): PayoutResult;

    /**
     * Look up the account holder's name before paying out.
     *
     * @return array{ok: bool, account_name: ?string, bank_name: ?string, message: ?string}
     */
    public function resolveBankAccount(string $bankCode, string $accountNumber, string $currency = 'NGN'): array;
}
