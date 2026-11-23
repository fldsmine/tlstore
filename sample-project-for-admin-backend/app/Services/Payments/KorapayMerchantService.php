<?php

namespace App\Services\Payments;

use App\Services\Payments\DTO\MerchantApiResult;
use App\Services\Payments\DTO\PayoutRequest;
use App\Services\Payments\DTO\PayoutResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Korapay MERCHANT operations for the backoffice: balance, balance history, single payouts
 * (disbursements) and payout verification. This is deliberately separate from KorapayGateway —
 * that driver collects customer charges (pay-ins) through the PaymentGateway contract, while
 * everything here moves money OUT of the merchant wallet and is only reachable from the
 * backoffice by admins.
 *
 * All calls use the secret key. Nothing here throws on an API/transport error: the result objects
 * report `ok` so the UI can show the provider's message instead of a stack trace, and so a payout
 * that errored mid-flight is never mistaken for a failed payout (see `verifyPayout()` — Korapay's
 * own guidance is to verify before giving value when a payout request errors out).
 */
class KorapayMerchantService implements MerchantWalletService
{
    /** Payout statuses Korapay returns that mean "in flight, not final". */
    private const IN_FLIGHT = ['processing', 'pending', 'initiated', 'queued'];

    public function name(): string
    {
        return 'korapay';
    }

    public function configured(): bool
    {
        return filled(config('services.korapay.secret_key'));
    }

    /** Test mode is derived from the key prefix — used to badge the backoffice UI. */
    public function isTestMode(): bool
    {
        return str_starts_with((string) config('services.korapay.secret_key'), 'sk_test');
    }

    /**
     * Currencies an admin is allowed to pay out in (config-driven, defaults to NGN).
     *
     * @return list<string>
     */
    public function payoutCurrencies(): array
    {
        $configured = config('services.korapay.payout_currencies', ['NGN']);

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code): string => strtoupper(trim((string) $code)),
            is_array($configured) ? $configured : explode(',', (string) $configured),
        ))));

        return $codes === [] ? ['NGN'] : $codes;
    }

    /** Per-payout ceiling from config; 0 or less disables the check. */
    public function maxPayoutAmount(): float
    {
        return (float) config('services.korapay.payout_max_amount', 1000000);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.korapay.base_url'))
            ->withToken((string) config('services.korapay.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /** Never surface a raw transport exception to a backoffice page — but do log it. */
    private function failure(Throwable $e, string $context): MerchantApiResult
    {
        Log::warning('Korapay merchant API call failed', [
            'context' => $context,
            'exception' => $e->getMessage(),
        ]);

        return MerchantApiResult::failure("The Korapay {$context} request could not be completed.");
    }

    /**
     * Wallet balances per currency: `[['currency' => 'NGN', 'available' => 1234.56, 'pending' => 0.0], …]`.
     */
    public function balances(): MerchantApiResult
    {
        if (! $this->configured()) {
            return MerchantApiResult::failure('Korapay is not configured — add the secret key under Settings → Services.');
        }

        try {
            $response = $this->client()->get('/merchant/api/v1/balances');

            if ($response->failed() || ! $response->json('status')) {
                return MerchantApiResult::failure((string) $this->messageFrom($response->json('message'), 'Korapay rejected the balance request.'));
            }

            $balances = [];

            foreach ((array) $response->json('data', []) as $currency => $figures) {
                if (! is_array($figures)) {
                    continue;
                }

                $balances[] = [
                    'currency' => strtoupper((string) $currency),
                    'available' => round((float) ($figures['available_balance'] ?? 0), 2),
                    'pending' => round((float) ($figures['pending_balance'] ?? 0), 2),
                    'issuing' => isset($figures['issuing_balance']) ? round((float) $figures['issuing_balance'], 2) : null,
                ];
            }

            if ($balances === []) {
                return MerchantApiResult::failure('Korapay returned no balances for this account.');
            }

            // Stable, money-first ordering: NGN (the payout currency) first, then largest balance.
            usort($balances, function (array $a, array $b): int {
                $aRank = $a['currency'] === 'NGN' ? 0 : 1;
                $bRank = $b['currency'] === 'NGN' ? 0 : 1;

                return [$aRank, -$a['available']] <=> [$bRank, -$b['available']];
            });

            return MerchantApiResult::success($balances);
        } catch (Throwable $e) {
            return $this->failure($e, 'balance');
        }
    }

    /**
     * Balance history (every credit/debit that moved the wallet), newest first as Korapay returns it.
     *
     * @param  array{currency?: ?string, direction?: ?string, date_from?: ?string, date_to?: ?string, limit?: ?int, starting_after?: ?string, ending_before?: ?string}  $filters
     * @return array{ok: bool, message: ?string, entries: list<array<string, mixed>>, has_more: bool, pointers: array<string, ?string>}
     */
    public function balanceHistory(array $filters = []): array
    {
        $empty = ['ok' => false, 'message' => null, 'entries' => [], 'has_more' => false, 'pointers' => ['first' => null, 'last' => null]];

        if (! $this->configured()) {
            return [...$empty, 'message' => 'Korapay is not configured — add the secret key under Settings → Services.'];
        }

        $query = array_filter([
            'currency' => $filters['currency'] ?? null,
            'direction' => $filters['direction'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'limit' => $filters['limit'] ?? null,
            'starting_after' => $filters['starting_after'] ?? null,
            'ending_before' => $filters['ending_before'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');

        try {
            $response = $this->client()->get('/merchant/api/v1/balances/history', $query);

            if ($response->failed() || ! $response->json('status')) {
                return [...$empty, 'message' => $this->messageFrom($response->json('message'), 'Korapay rejected the balance history request.')];
            }

            // Korapay documents `data` as a single object but returns a list in practice — normalise both.
            $raw = $response->json('data', []);
            $rows = array_is_list((array) $raw) ? (array) $raw : [$raw];

            $entries = [];

            foreach ($rows as $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $entries[] = [
                    'pointer' => (string) ($row['pointer'] ?? ''),
                    'amount' => round((float) ($row['amount'] ?? 0), 2),
                    'currency' => strtoupper((string) ($row['currency'] ?? '')),
                    'balance_before' => round((float) ($row['balance_before'] ?? 0), 2),
                    'balance_after' => round((float) ($row['balance_after'] ?? 0), 2),
                    'date_created' => (string) ($row['date_created'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'direction' => strtolower((string) ($row['direction'] ?? '')),
                    'source' => (string) ($row['source'] ?? ''),
                    'source_reference' => (string) ($row['source_reference'] ?? ''),
                ];
            }

            return [
                'ok' => true,
                'message' => null,
                'entries' => $entries,
                'has_more' => (bool) $response->json('has_more', false),
                'pointers' => [
                    'first' => $entries === [] ? null : $entries[0]['pointer'],
                    'last' => $entries === [] ? null : $entries[count($entries) - 1]['pointer'],
                ],
            ];
        } catch (Throwable $e) {
            Log::warning('Korapay merchant API call failed', ['context' => 'balance history', 'exception' => $e->getMessage()]);

            return [...$empty, 'message' => 'The Korapay balance history request could not be completed.'];
        }
    }

    /**
     * Request a single payout to a bank account.
     *
     * A transport/5xx failure returns `ok: false` — Korapay may still have accepted the payout, so
     * the caller MUST re-verify by reference instead of retrying blindly.
     */
    public function disburse(PayoutRequest $request): PayoutResult
    {
        if (! $this->configured()) {
            return PayoutResult::unknown('Korapay is not configured — add the secret key under Settings → Services.');
        }

        $overLimit = $this->overPayoutLimit($request);

        if ($overLimit !== null) {
            return $overLimit;
        }

        $payload = [
            'reference' => $request->reference,
            'destination' => [
                'type' => $request->destinationType,
                'amount' => round($request->amount, 2),
                'currency' => $request->currency,
                'narration' => $request->narration,
                'bank_account' => array_filter([
                    'bank' => $request->bankCode,
                    'account' => $request->accountNumber,
                    'account_name' => $request->accountName,
                ], fn ($value): bool => filled($value)),
                'customer' => array_filter([
                    'name' => $request->customerName,
                    'email' => $request->customerEmail,
                ], fn ($value): bool => filled($value)),
            ],
        ];

        if ($request->metadata !== []) {
            $payload['metadata'] = $request->metadata;
        }

        try {
            $response = $this->client()->post('/merchant/api/v1/transactions/disburse', $payload);

            $data = (array) $response->json('data', []);
            $message = (string) $this->messageFrom($response->json('message'), 'Korapay rejected the payout request.');

            // 5xx/timeout: Korapay may have accepted the payout — report UNKNOWN so the admin verifies
            // by reference (Korapay's documented guidance) instead of re-sending the money.
            if ($response->serverError() || $response->json('status') === null) {
                return PayoutResult::unknown($message, ['payload' => $payload, 'response' => $data, 'http_status' => $response->status()]);
            }

            if ($response->failed() || ! $response->json('status')) {
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    amount: round((float) ($data['amount'] ?? $request->amount), 2),
                    currency: (string) ($data['currency'] ?? $request->currency),
                    reference: (string) ($data['reference'] ?? $request->reference),
                    message: $message,
                    data: ['payload' => $payload, 'response' => $data, 'http_status' => $response->status()],
                );
            }

            return new PayoutResult(
                ok: true,
                status: $this->normaliseStatus((string) ($data['status'] ?? self::IN_FLIGHT[0])),
                amount: round((float) ($data['amount'] ?? $request->amount), 2),
                fee: round((float) ($data['fee'] ?? 0), 2),
                currency: (string) ($data['currency'] ?? $request->currency),
                reference: (string) ($data['reference'] ?? $request->reference),
                // Korapay's own handle for the payout — recorded on the audit row right away, so the
                // reference alone is enough to follow the money up without a second API call.
                traceId: filled($data['trace_id'] ?? null) ? (string) $data['trace_id'] : null,
                message: $this->messageFrom($data['message'] ?? null, $message),
                data: ['payload' => $payload, 'response' => $data, 'http_status' => $response->status()],
            );
        } catch (Throwable $e) {
            Log::warning('Korapay merchant API call failed', ['context' => 'payout disburse', 'exception' => $e->getMessage()]);

            return PayoutResult::unknown('The payout request could not be completed — verify the reference before retrying.');
        }
    }

    /**
     * The per-payout ceiling as a definitive rejection. Checked inside disburse() so the limit holds
     * even if the service is called from somewhere other than the backoffice form.
     */
    private function overPayoutLimit(PayoutRequest $request): ?PayoutResult
    {
        $max = $this->maxPayoutAmount();

        if ($max <= 0 || $request->amount <= $max) {
            return null;
        }

        return new PayoutResult(
            ok: true,
            status: PayoutResult::STATUS_FAILED,
            amount: round($request->amount, 2),
            currency: $request->currency,
            reference: $request->reference,
            message: 'This payout is above the configured ceiling of '.number_format($max, 2).' — nothing was sent.',
        );
    }

    /**
     * Payout Verification API — the authoritative status of a payout by OUR reference.
     */
    public function verifyPayout(string $reference): PayoutResult
    {
        if (! $this->configured()) {
            return PayoutResult::unknown('Korapay is not configured — add the secret key under Settings → Services.');
        }

        if (trim($reference) === '') {
            return PayoutResult::unknown('Enter a payout reference to verify.');
        }

        try {
            $response = $this->client()->get('/merchant/api/v1/transactions/'.rawurlencode(trim($reference)));

            $data = (array) $response->json('data', []);

            // An unknown reference is a definitive "not found", not a transport problem.
            if ($response->status() === 404) {
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    reference: trim($reference),
                    message: $this->messageFrom($response->json('message'), 'Korapay has no payout with that reference.'),
                    data: ['response' => $data, 'http_status' => 404],
                );
            }

            if ($response->serverError() || $response->status() === 0) {
                return PayoutResult::unknown('Korapay could not be reached — the payout status is unknown, try again.', ['http_status' => $response->status()]);
            }

            if ($response->failed() || ! $response->json('status')) {
                return PayoutResult::unknown((string) $this->messageFrom($response->json('message'), 'Korapay rejected the payout verification request.'), ['response' => $data, 'http_status' => $response->status()]);
            }

            return new PayoutResult(
                ok: true,
                status: $this->normaliseStatus((string) ($data['status'] ?? PayoutResult::STATUS_PENDING)),
                amount: round((float) ($data['amount'] ?? 0), 2),
                fee: round((float) ($data['fee'] ?? 0), 2),
                currency: strtoupper((string) ($data['currency'] ?? '')),
                reference: (string) ($data['reference'] ?? $reference),
                traceId: filled($data['trace_id'] ?? null) ? (string) $data['trace_id'] : null,
                message: $this->messageFrom($data['message'] ?? $data['narration'] ?? null, null),
                data: $data,
            );
        } catch (Throwable $e) {
            Log::warning('Korapay merchant API call failed', ['context' => 'payout verify', 'exception' => $e->getMessage()]);

            return PayoutResult::unknown('Korapay could not be reached — the payout status is unknown, try again.');
        }
    }

    /**
     * Resolve the account holder's name for a destination bank account before paying out.
     *
     * @return array{ok: bool, account_name: ?string, bank_name: ?string, message: ?string}
     */
    public function resolveBankAccount(string $bankCode, string $accountNumber, string $currency = 'NGN'): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'account_name' => null, 'bank_name' => null, 'message' => 'Korapay is not configured.'];
        }

        try {
            $response = $this->client()->post('/merchant/api/v1/misc/banks/resolve', [
                'bank' => $bankCode,
                'account' => $accountNumber,
                'currency' => strtoupper($currency),
            ]);

            if ($response->failed() || ! $response->json('status')) {
                return [
                    'ok' => false,
                    'account_name' => null,
                    'bank_name' => null,
                    'message' => $this->messageFrom($response->json('message'), 'That account could not be resolved.'),
                ];
            }

            return [
                'ok' => true,
                'account_name' => filled($response->json('data.account_name')) ? (string) $response->json('data.account_name') : null,
                'bank_name' => filled($response->json('data.bank_name')) ? (string) $response->json('data.bank_name') : null,
                'message' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('Korapay merchant API call failed', ['context' => 'account resolve', 'exception' => $e->getMessage()]);

            return ['ok' => false, 'account_name' => null, 'bank_name' => null, 'message' => 'The account resolution request could not be completed.'];
        }
    }

    /** Korapay's payout statuses collapsed onto the four states the app stores. */
    public function normaliseStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'success', 'successful', 'completed', 'credited' => PayoutResult::STATUS_SUCCESS,
            'failed', 'reversed', 'cancelled', 'canceled', 'expired', 'declined' => PayoutResult::STATUS_FAILED,
            'processing', 'initiated', 'queued', 'in_progress' => PayoutResult::STATUS_PROCESSING,
            'pending' => PayoutResult::STATUS_PENDING,
            default => PayoutResult::STATUS_PENDING,
        };
    }

    /**
     * Korapay mixes `message` and `narration` between endpoints; `$fallback` keeps a UI-friendly
     * sentence when the response carries neither.
     */
    private function messageFrom(mixed $message, ?string $fallback = null): ?string
    {
        return filled($message) ? (string) $message : $fallback;
    }
}
