<?php

namespace App\Services\Payments;

use App\Services\Payments\DTO\MerchantApiResult;
use App\Services\Payments\DTO\PayoutRequest;
use App\Services\Payments\DTO\PayoutResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Flutterwave MERCHANT operations for the backoffice: wallet balances, wallet (balance) history,
 * single payouts (transfers) and payout verification. Deliberately separate from
 * FlutterwaveGateway — that driver COLLECTS charges through the PaymentGateway contract, while
 * everything here moves money OUT of the merchant wallet and is only reachable from the backoffice.
 *
 * Endpoints (v3, all authenticated with the secret key):
 *   GET  /v3/balances                      → per-currency wallet balances
 *   GET  /v3/wallet/statement              → wallet (balance) history, page-based
 *   POST /v3/transfers                     → initiate a single bank-account payout
 *   GET  /v3/transfers/{id}                → transfer by Flutterwave id
 *   GET  /v3/transfers?reference=…         → transfers by OUR reference
 *   POST /v3/accounts/resolve              → bank-account name lookup before paying out
 *
 * Nothing here throws on an API/transport error: results carry `ok` so the UI shows Flutterwave's
 * own message, and a payout that errored mid-flight is never mistaken for a failed payout (see
 * verifyPayout() — verify by reference before retrying).
 */
class FlutterwaveMerchantService implements MerchantWalletService
{
    /** Flutterwave payout statuses that mean "in flight, not final". */
    private const IN_FLIGHT = ['new', 'pending', 'processing', 'queued', 'initiated'];

    public function name(): string
    {
        return 'flutterwave';
    }

    public function configured(): bool
    {
        return filled(config('services.flutterwave.secret_key'));
    }

    /** Test mode is derived from the key prefix — used to badge the backoffice UI. */
    public function isTestMode(): bool
    {
        return str_contains((string) config('services.flutterwave.secret_key'), 'FLWSECK_TEST');
    }

    /**
     * Currencies the backoffice may pay out in (config-driven). Flutterwave transfers debit ONE
     * wallet (the transfer currency, or `debit_currency`), so this is also the wallet list shown.
     *
     * @return list<string>
     */
    public function payoutCurrencies(): array
    {
        $configured = config('services.flutterwave.payout_currencies', ['NGN']);

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code): string => strtoupper(trim((string) $code)),
            is_array($configured) ? $configured : explode(',', (string) $configured),
        ))));

        return $codes === [] ? ['NGN'] : $codes;
    }

    /** Per-payout ceiling from config; 0 or less disables the check. */
    public function maxPayoutAmount(): float
    {
        return (float) config('services.flutterwave.payout_max_amount', 1000000);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.flutterwave.base_url', 'https://api.flutterwave.com'))
            ->withToken((string) config('services.flutterwave.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /** Flutterwave answers 200 with `status: success|error`; both shapes carry a human `message`. */
    private function succeeded(Response $response): bool
    {
        return ! $response->failed() && $response->json('status') === 'success';
    }

    private function messageFrom(Response $response, string $fallback): string
    {
        $message = $response->json('message');

        return filled($message) ? (string) $message : $fallback;
    }

    /** Never surface a raw transport exception to a backoffice page — but do log it. */
    private function failed(Throwable $e, string $context): void
    {
        Log::warning('Flutterwave merchant API call failed', [
            'context' => $context,
            'exception' => $e->getMessage(),
        ]);
    }

    /**
     * Wallet balances: `[['currency' => 'NGN', 'available' => 1234.56, 'pending' => 0.0, 'issuing' => null], …]`.
     *
     * Flutterwave's balance list carries a single figure per currency, so `available` is it and
     * `pending` is always zero (the key stays so the shared balance UI needs no special casing).
     */
    public function balances(): MerchantApiResult
    {
        if (! $this->configured()) {
            return MerchantApiResult::failure('Flutterwave is not configured — add the secret key under Settings → Services.');
        }

        try {
            $response = $this->client()->get('/v3/balances');

            if (! $this->succeeded($response)) {
                return MerchantApiResult::failure($this->messageFrom($response, 'Flutterwave rejected the balance request.'));
            }

            // A currency filter makes the API answer with ONE object instead of a list; normalise both.
            $raw = $response->json('data', []);
            $rows = array_is_list((array) $raw) ? (array) $raw : [$raw];

            $balances = [];

            foreach ($rows as $row) {
                if (! is_array($row) || blank($row['currency'] ?? null)) {
                    continue;
                }

                $balances[] = [
                    'currency' => strtoupper((string) $row['currency']),
                    'available' => round((float) ($row['balance'] ?? 0), 2),
                    'pending' => 0.0,
                    'issuing' => null,
                ];
            }

            if ($balances === []) {
                return MerchantApiResult::failure('Flutterwave returned no balances for this account.');
            }

            // Newest-first by size, with the payout currency on top.
            usort($balances, function (array $a, array $b): int {
                $aRank = $a['currency'] === $this->payoutCurrencies()[0] ? 0 : 1;
                $bRank = $b['currency'] === $this->payoutCurrencies()[0] ? 0 : 1;

                return [$aRank, -$a['available']] <=> [$bRank, -$b['available']];
            });

            return MerchantApiResult::success($balances);
        } catch (Throwable $e) {
            $this->failed($e, 'balance');

            return MerchantApiResult::failure('The Flutterwave balance request could not be completed.');
        }
    }

    /**
     * Wallet (balance) history — every credit/debit that moved the wallet.
     *
     * Flutterwave pages by number (`data.page_info.total_pages`), so each entry's `pointer` is the
     * string page it came from and the caller walks the pages with starting_after/ending_before
     * exactly like the opaque-pointer provider does.
     *
     * @param  array{currency?: ?string, direction?: ?string, date_from?: ?string, date_to?: ?string, limit?: ?int, starting_after?: ?string, ending_before?: ?string}  $filters
     * @return array{ok: bool, message: ?string, entries: list<array<string, mixed>>, has_more: bool, pointers: array<string, ?string>}
     */
    public function balanceHistory(array $filters = []): array
    {
        $empty = ['ok' => false, 'message' => null, 'entries' => [], 'has_more' => false, 'pointers' => ['first' => null, 'last' => null]];

        if (! $this->configured()) {
            return [...$empty, 'message' => 'Flutterwave is not configured — add the secret key under Settings → Services.'];
        }

        $page = max(1, (int) ($filters['starting_after'] ?? $filters['page'] ?? 1));

        // `from`/`to` are required by the API; default to the last 30 days when the admin hasn't
        // narrowed the window (the page's date filters are optional).
        $from = filled($filters['date_from'] ?? null) ? (string) $filters['date_from'] : now()->subDays(30)->toDateString();
        $to = filled($filters['date_to'] ?? null) ? (string) $filters['date_to'] : now()->toDateString();

        $query = array_filter([
            'from' => $from,
            'to' => $to,
            'currency' => filled($filters['currency'] ?? null) ? strtoupper((string) $filters['currency']) : null,
            'type' => match (strtolower((string) ($filters['direction'] ?? ''))) {
                'credit' => 'C',
                'debit' => 'D',
                default => null,
            },
            'page' => $page,
            'include_transaction_type' => 'true',
        ], fn ($value): bool => $value !== null && $value !== '');

        try {
            $response = $this->client()->get('/v3/wallet/statement', $query);

            if (! $this->succeeded($response)) {
                return [...$empty, 'message' => $this->messageFrom($response, 'Flutterwave rejected the wallet statement request.')];
            }

            $rows = (array) $response->json('data.transactions', []);
            $entries = [];

            foreach ($rows as $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $entries[] = [
                    'pointer' => (string) $page,
                    'amount' => round((float) ($row['amount'] ?? 0), 2),
                    'currency' => strtoupper((string) ($row['currency'] ?? '')),
                    'balance_before' => round((float) ($row['balance_before'] ?? 0), 2),
                    'balance_after' => round((float) ($row['balance_after'] ?? 0), 2),
                    'date_created' => (string) ($row['date'] ?? ''),
                    'description' => (string) ($row['remarks'] ?? ''),
                    'direction' => strtolower((string) ($row['type'] ?? '')) === 'c' ? 'credit' : 'debit',
                    'source' => (string) ($row['statement_type'] ?? ''),
                    'source_reference' => (string) ($row['reference'] ?? ''),
                    'transaction_type' => (string) ($row['transaction_type'] ?? ''),
                ];
            }

            $totalPages = (int) ($response->json('data.page_info.total_pages') ?? $page);

            return [
                'ok' => true,
                'message' => null,
                'entries' => $entries,
                'has_more' => $page < $totalPages,
                'pointers' => [
                    'first' => '1',
                    'last' => (string) $page,
                ],
            ];
        } catch (Throwable $e) {
            $this->failed($e, 'wallet statement');

            return [...$empty, 'message' => 'The Flutterwave wallet statement request could not be completed.'];
        }
    }

    /**
     * Request a single payout (transfer) to a bank account.
     *
     * A transport/5xx failure returns `ok: false` with `unknown` — Flutterwave may still have queued
     * the transfer, so the caller MUST re-verify by reference instead of retrying blindly.
     */
    public function disburse(PayoutRequest $request): PayoutResult
    {
        if (! $this->configured()) {
            return PayoutResult::unknown('Flutterwave is not configured — add the secret key under Settings → Services.');
        }

        $overLimit = $this->overPayoutLimit($request);

        if ($overLimit !== null) {
            return $overLimit;
        }

        $payload = array_filter([
            'account_bank' => $request->bankCode,
            'account_number' => $request->accountNumber,
            'amount' => round($request->amount, 2),
            'currency' => $request->currency,
            'debit_currency' => $request->debitCurrency,
            'narration' => $request->narration,
            'reference' => $request->reference,
            'beneficiary_name' => $request->customerName,
        ], fn ($value): bool => $value !== null && $value !== '');

        try {
            $response = $this->client()->post('/v3/transfers', $payload);
            $data = (array) $response->json('data', []);

            // 5xx/timeout: Flutterwave may have queued the transfer — report UNKNOWN so the admin
            // verifies by reference (rather than re-sending money that may already be on its way).
            if ($response->serverError() || $response->json('status') === null) {
                return PayoutResult::unknown(
                    $this->messageFrom($response, 'Flutterwave did not answer definitively — verify the reference before retrying.'),
                    ['payload' => $payload, 'response' => $data, 'http_status' => $response->status()],
                );
            }

            if (! $this->succeeded($response)) {
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    amount: round((float) ($data['amount'] ?? $request->amount), 2),
                    currency: (string) ($data['currency'] ?? $request->currency),
                    reference: (string) ($data['reference'] ?? $request->reference),
                    message: $this->messageFrom($response, 'Flutterwave rejected the payout request.'),
                    data: ['payload' => $payload, 'response' => $data, 'http_status' => $response->status()],
                );
            }

            return new PayoutResult(
                ok: true,
                status: $this->normaliseStatus((string) ($data['status'] ?? self::IN_FLIGHT[0]), $data),
                amount: round((float) ($data['amount'] ?? $request->amount), 2),
                fee: round((float) ($data['fee'] ?? 0), 2),
                currency: strtoupper((string) ($data['currency'] ?? $request->currency)),
                reference: (string) ($data['reference'] ?? $request->reference),
                gatewayId: isset($data['id']) ? (string) $data['id'] : null,
                message: $this->payoutMessage($data, $response, 'Transfer queued successfully.'),
                data: ['payload' => $payload, 'response' => $data, 'http_status' => $response->status()],
            );
        } catch (Throwable $e) {
            $this->failed($e, 'payout disburse');

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
     * Payout verification.
     *
     * Accepts either Flutterwave's numeric transfer id or OUR reference: a numeric input is looked up
     * directly (`GET /v3/transfers/{id}`), anything else is matched through
     * `GET /v3/transfers?reference=…`. Returns the same PayoutResult shape as Korapay so the
     * backoffice page and the audit-row update are shared code.
     */
    public function verifyPayout(string $referenceOrId): PayoutResult
    {
        if (! $this->configured()) {
            return PayoutResult::unknown('Flutterwave is not configured — add the secret key under Settings → Services.');
        }

        $needle = trim($referenceOrId);

        if ($needle === '') {
            return PayoutResult::unknown('Enter a payout reference to verify.');
        }

        try {
            $response = ctype_digit($needle)
                ? $this->client()->get('/v3/transfers/'.rawurlencode($needle))
                : $this->client()->get('/v3/transfers', ['reference' => $needle, 'page_size' => 10]);

            // A transfer that does not exist is a definitive "not found", not a transport problem.
            if ($response->status() === 404) {
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    reference: $needle,
                    message: $this->messageFrom($response, 'Flutterwave has no transfer with that reference.'),
                    data: ['response' => (array) $response->json('data', []), 'http_status' => 404],
                );
            }

            if ($response->serverError() || $response->status() === 0) {
                return PayoutResult::unknown('Flutterwave could not be reached — the payout status is unknown, try again.', ['http_status' => $response->status()]);
            }

            if (! $this->succeeded($response)) {
                return PayoutResult::unknown(
                    $this->messageFrom($response, 'Flutterwave rejected the payout verification request.'),
                    ['response' => (array) $response->json('data', []), 'http_status' => $response->status()],
                );
            }

            $data = $this->pickTransfer($response, $needle);

            if ($data === []) {
                // Listed successfully but nothing matched OUR reference: Flutterwave has no transfer
                // under that name, which is a definitive answer (not a transport failure).
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    reference: $needle,
                    message: 'Flutterwave has no transfer with that reference.',
                    data: ['response' => (array) $response->json('data', []), 'http_status' => $response->status()],
                );
            }

            return new PayoutResult(
                ok: true,
                status: $this->normaliseStatus((string) ($data['status'] ?? ''), $data),
                amount: round((float) ($data['amount'] ?? 0), 2),
                fee: round((float) ($data['fee'] ?? 0), 2),
                currency: strtoupper((string) ($data['currency'] ?? '')),
                reference: (string) ($data['reference'] ?? $needle),
                gatewayId: isset($data['id']) ? (string) $data['id'] : null,
                message: $this->payoutMessage($data, $response, null),
                data: $data,
            );
        } catch (Throwable $e) {
            $this->failed($e, 'payout verify');

            return PayoutResult::unknown('Flutterwave could not be reached — the payout status is unknown, try again.');
        }
    }

    /**
     * Resolve the account holder's name for a destination bank account before paying out.
     * Flutterwave supports this for Nigerian banks only.
     *
     * @return array{ok: bool, account_name: ?string, bank_name: ?string, message: ?string}
     */
    public function resolveBankAccount(string $bankCode, string $accountNumber, string $currency = 'NGN'): array
    {
        $denied = ['ok' => false, 'account_name' => null, 'bank_name' => null, 'message' => null];

        if (! $this->configured()) {
            return [...$denied, 'message' => 'Flutterwave is not configured.'];
        }

        if (strtoupper($currency) !== 'NGN') {
            return [...$denied, 'message' => 'Account name lookup is only available for NGN transfers.'];
        }

        try {
            $response = $this->client()->post('/v3/accounts/resolve', [
                'account_bank' => $bankCode,
                'account_number' => $accountNumber,
            ]);

            if (! $this->succeeded($response)) {
                return [...$denied, 'message' => $this->messageFrom($response, 'That account could not be resolved.')];
            }

            return [
                'ok' => true,
                'account_name' => filled($response->json('data.account_name')) ? (string) $response->json('data.account_name') : null,
                'bank_name' => filled($response->json('data.bank_name')) ? (string) $response->json('data.bank_name') : null,
                'message' => null,
            ];
        } catch (Throwable $e) {
            $this->failed($e, 'account resolve');

            return [...$denied, 'message' => 'The account resolution request could not be completed.'];
        }
    }

    /**
     * Pull the transfer out of a verify response: `/transfers/{id}` answers with ONE object, while
     * `/transfers?reference=` answers with a list — take the newest match.
     *
     * @return array<string, mixed>
     */
    private function pickTransfer(Response $response, string $needle): array
    {
        $raw = (array) $response->json('data', []);

        if ($raw === []) {
            return [];
        }

        $rows = array_is_list($raw) ? $raw : [$raw];

        // Newest first, then the first row that belongs to the reference we asked about.
        usort($rows, fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (ctype_digit($needle) || (string) ($row['reference'] ?? '') === $needle) {
                return $row;
            }
        }

        return [];
    }

    /**
     * Flutterwave's transfer statuses collapsed onto the states the app stores.
     *
     * @param  array<string, mixed>  $data
     */
    public function normaliseStatus(string $status, array $data = []): string
    {
        $normalised = match (strtolower(trim($status))) {
            'successful', 'success', 'completed', 'completed_successfully' => PayoutResult::STATUS_SUCCESS,
            'failed', 'cancelled', 'canceled', 'reversed', 'declined' => PayoutResult::STATUS_FAILED,
            'new', 'processing', 'initiated', 'queued', 'in_progress' => PayoutResult::STATUS_PROCESSING,
            'pending' => PayoutResult::STATUS_PENDING,
            default => PayoutResult::STATUS_PENDING,
        };

        // A queued transfer that still needs approval on the Flutterwave dashboard is not on its way
        // yet — keep it pending so nobody re-sends it thinking the first attempt failed.
        if ((int) ($data['requires_approval'] ?? 0) === 1 && (int) ($data['is_approved'] ?? 1) === 0 && $normalised === PayoutResult::STATUS_PROCESSING) {
            return PayoutResult::STATUS_PENDING;
        }

        return $normalised;
    }

    /**
     * The most useful sentence about a transfer: Flutterwave's `complete_message` explains the
     * non-success cases ("Insufficient balance", "Transaction was successful"), `message` is the
     * request-level answer.
     *
     * @param  array<string, mixed>  $data
     */
    private function payoutMessage(array $data, Response $response, ?string $fallback): ?string
    {
        foreach ([$data['complete_message'] ?? null, $data['message'] ?? null, $response->json('message')] as $candidate) {
            if (filled($candidate)) {
                return (string) $candidate;
            }
        }

        if ((int) ($data['requires_approval'] ?? 0) === 1 && (int) ($data['is_approved'] ?? 1) === 0) {
            return 'Transfer queued — approve it on the Flutterwave dashboard before it leaves.';
        }

        return $fallback;
    }
}
