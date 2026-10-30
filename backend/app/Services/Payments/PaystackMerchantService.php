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
 * Paystack MERCHANT operations for the backoffice: balance, balance ledger (history), single
 * payouts (transfers) and transfer verification. Deliberately separate from PaystackGateway — that
 * driver COLLECTS charges through the PaymentGateway contract and verifies bank accounts, while
 * everything here moves money OUT of the Paystack balance and is only reachable from the backoffice.
 *
 * Endpoints (all authenticated with the secret key, all amounts in subunits — kobo/pesewas):
 *   GET  /balance                      → the balance per currency
 *   GET  /balance/ledger               → every credit/debit that moved the balance (page-based)
 *   POST /transferrecipient            → create/retrieve the recipient for the account being paid
 *   POST /transfer                     → initiate the single transfer to that recipient
 *   GET  /transfer/verify/{reference}  → authoritative transfer status, by OUR reference
 *   GET  /transfer/{id_or_code}        → transfer by Paystack's own id/code (TRF_…)
 *   GET  /bank/resolve                 → account holder name before paying out
 *
 * Nothing here throws on an API/transport error: results carry `ok` so the UI shows Paystack's own
 * message, and a payout that errored mid-flight is never mistaken for a failed payout (see
 * verifyPayout() — verify by reference before retrying).
 */
class PaystackMerchantService implements MerchantWalletService
{
    /**
     * Paystack transfer references must be 16–50 characters of lowercase letters, digits, dashes
     * and underscores, while our own references are `PSK-` + 12 uppercase alphanumerics. The wire
     * reference below is therefore a DETERMINISTIC mapping of ours — same payout, same string —
     * so verify-by-reference still works without storing a second reference anywhere.
     */
    private const WIRE_REFERENCE_MIN = 16;

    private const WIRE_REFERENCE_MAX = 50;

    /** Transfer recipient types by payout currency (Paystack's own channel names). */
    private const RECIPIENT_TYPES = ['NGN' => 'nuban', 'GHS' => 'ghipss'];

    public function name(): string
    {
        return 'paystack';
    }

    public function configured(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /** Test mode is derived from the key prefix — used to badge the backoffice UI. */
    public function isTestMode(): bool
    {
        return str_starts_with((string) config('services.paystack.secret_key'), 'sk_test');
    }

    /**
     * Currencies the backoffice may pay out in (config-driven). Paystack transfers are funded from
     * the single `balance` wallet, so this is also the wallet list shown.
     *
     * @return list<string>
     */
    public function payoutCurrencies(): array
    {
        $configured = config('services.paystack.payout_currencies', ['NGN']);

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code): string => strtoupper(trim((string) $code)),
            is_array($configured) ? $configured : explode(',', (string) $configured),
        ))));

        return $codes === [] ? ['NGN'] : $codes;
    }

    /** Per-payout ceiling from config; 0 or less disables the check. */
    public function maxPayoutAmount(): float
    {
        return (float) config('services.paystack.payout_max_amount', 1000000);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paystack.base_url', 'https://api.paystack.co'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /**
     * Paystack answers 200 with `status: true|false`; both shapes carry a human `message`.
     * (It is also the header Paystack's own SDKs check, so a 2xx with status false is an error.)
     */
    private function succeeded(Response $response): bool
    {
        return ! $response->failed() && $response->json('status') === true;
    }

    private function messageFrom(Response $response, string $fallback): string
    {
        $message = $response->json('message');

        return filled($message) ? (string) $message : $fallback;
    }

    /** Never surface a raw transport exception to a backoffice page — but do log it. */
    private function failed(Throwable $e, string $context): void
    {
        Log::warning('Paystack merchant API call failed', [
            'context' => $context,
            'exception' => $e->getMessage(),
        ]);
    }

    /* ----------------------------------------------------------------------------- balance */

    /**
     * Balance per currency: `[['currency' => 'NGN', 'available' => 17000.0, 'pending' => 0.0, 'issuing' => null], …]`.
     *
     * Paystack reports one figure per currency, so `available` is it and `pending` is always zero
     * (the key stays so the shared balance UI needs no special casing).
     */
    public function balances(): MerchantApiResult
    {
        if (! $this->configured()) {
            return MerchantApiResult::failure('Paystack is not configured — add the secret key under Settings → Services.');
        }

        try {
            $response = $this->client()->get('/balance');

            if (! $this->succeeded($response)) {
                return MerchantApiResult::failure($this->messageFrom($response, 'Paystack rejected the balance request.'));
            }

            $raw = $response->json('data', []);
            $rows = array_is_list((array) $raw) ? (array) $raw : [$raw];

            $balances = [];

            foreach ($rows as $row) {
                if (! is_array($row) || blank($row['currency'] ?? null)) {
                    continue;
                }

                $balances[] = [
                    'currency' => strtoupper((string) $row['currency']),
                    'available' => round($this->toMajor((float) ($row['balance'] ?? 0)), 2),
                    'pending' => 0.0,
                    'issuing' => null,
                ];
            }

            if ($balances === []) {
                return MerchantApiResult::failure('Paystack returned no balance for this account.');
            }

            // Stable, money-first ordering: the payout currency on top, then the largest balance.
            $preferred = $this->payoutCurrencies()[0];

            usort($balances, fn (array $a, array $b): int => [
                $a['currency'] === $preferred ? 0 : 1, -$a['available'],
            ] <=> [
                $b['currency'] === $preferred ? 0 : 1, -$b['available'],
            ]);

            return MerchantApiResult::success($balances);
        } catch (Throwable $e) {
            $this->failed($e, 'balance');

            return MerchantApiResult::failure('The Paystack balance request could not be completed.');
        }
    }

    /* --------------------------------------------------------------------- balance history */

    /**
     * Balance ledger — every credit/debit that moved the balance.
     *
     * Paystack pages by number (`meta.page` of `meta.pageCount`), so each entry's `pointer` is the
     * string page it came from and the caller walks pages with starting_after/ending_before exactly
     * like an opaque-pointer provider.
     *
     * The ledger endpoint does not document currency/direction/date filters, so whatever Paystack
     * may ignore is re-applied here: a filtered page only ever shows rows that match the filter.
     *
     * @param  array{currency?: ?string, direction?: ?string, date_from?: ?string, date_to?: ?string, limit?: ?int, starting_after?: ?string, ending_before?: ?string}  $filters
     * @return array{ok: bool, message: ?string, entries: list<array<string, mixed>>, has_more: bool, pointers: array<string, ?string>}
     */
    public function balanceHistory(array $filters = []): array
    {
        $empty = ['ok' => false, 'message' => null, 'entries' => [], 'has_more' => false, 'pointers' => ['first' => null, 'last' => null]];

        if (! $this->configured()) {
            return [...$empty, 'message' => 'Paystack is not configured — add the secret key under Settings → Services.'];
        }

        $page = max(1, (int) ($filters['starting_after'] ?? $filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['limit'] ?? 50)));

        try {
            $response = $this->client()->get('/balance/ledger', [
                'perPage' => $perPage,
                'page' => $page,
            ]);

            if (! $this->succeeded($response)) {
                return [...$empty, 'message' => $this->messageFrom($response, 'Paystack rejected the balance ledger request.')];
            }

            $rows = (array) $response->json('data', []);
            $entries = [];

            foreach ($rows as $row) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                // `difference` is signed and in subunits: positive = money in, negative = money out.
                $difference = (float) ($row['difference'] ?? 0);
                $balance = (float) ($row['balance'] ?? 0);

                $entries[] = [
                    'pointer' => (string) $page,
                    'amount' => round(abs($this->toMajor($difference)), 2),
                    'currency' => strtoupper((string) ($row['currency'] ?? '')),
                    // Paystack reports the balance AFTER the movement; the "before" is its inverse.
                    'balance_before' => round($this->toMajor($balance - $difference), 2),
                    'balance_after' => round($this->toMajor($balance), 2),
                    'date_created' => (string) ($row['createdAt'] ?? ''),
                    'description' => (string) ($row['reason'] ?? ''),
                    'direction' => $difference < 0 ? 'debit' : 'credit',
                    'source' => (string) ($row['model_responsible'] ?? ''),
                    'source_reference' => (string) ($row['model_row'] ?? ''),
                    'transaction_type' => '',
                ];
            }

            $entries = $this->filterLedgerRows($entries, $filters);

            $pageCount = (int) ($response->json('meta.pageCount') ?? $page);

            return [
                'ok' => true,
                'message' => null,
                'entries' => $entries,
                'has_more' => $page < $pageCount,
                'pointers' => [
                    'first' => '1',
                    'last' => (string) $page,
                ],
            ];
        } catch (Throwable $e) {
            $this->failed($e, 'balance ledger');

            return [...$empty, 'message' => 'The Paystack balance ledger request could not be completed.'];
        }
    }

    /**
     * Apply the ledger filters to a page locally, so the page never shows rows the admin filtered
     * out. Paystack's ledger endpoint may honour some of these too — this makes it irrelevant.
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function filterLedgerRows(array $entries, array $filters): array
    {
        $currency = strtoupper(trim((string) ($filters['currency'] ?? '')));
        $direction = strtolower(trim((string) ($filters['direction'] ?? '')));

        // The page sends `Y-m-d-00-00-00` style strings (Korapay's format); only the date matters here.
        $from = filled($filters['date_from'] ?? null) ? substr((string) $filters['date_from'], 0, 10) : null;
        $to = filled($filters['date_to'] ?? null) ? substr((string) $filters['date_to'], 0, 10) : null;

        return array_values(array_filter($entries, function (array $entry) use ($currency, $direction, $from, $to): bool {
            if ($currency !== '' && ($entry['currency'] ?? '') !== $currency) {
                return false;
            }

            if ($direction !== '' && ($entry['direction'] ?? '') !== $direction) {
                return false;
            }

            $date = substr((string) ($entry['date_created'] ?? ''), 0, 10);

            if ($from !== null && $date !== '' && $date < $from) {
                return false;
            }

            return ! ($to !== null && $date !== '' && $date > $to);
        }));
    }

    /* ------------------------------------------------------------------------------ payouts */

    /**
     * Request a single payout (transfer) to a bank account.
     *
     * Paystack pays a transfer RECIPIENT, so the account is first turned into (or matched with) a
     * recipient — the API returns the existing record when the account number is already known, so
     * repeat payouts to the same account do not pile up duplicates. Only then is the transfer
     * initiated.
     *
     * A transport/5xx failure returns `ok: false` with `unknown` — Paystack may still have queued
     * the transfer, so the caller MUST re-verify by reference instead of retrying blindly.
     */
    public function disburse(PayoutRequest $request): PayoutResult
    {
        if (! $this->configured()) {
            return PayoutResult::unknown('Paystack is not configured — add the secret key under Settings → Services.');
        }

        $overLimit = $this->overPayoutLimit($request);

        if ($overLimit !== null) {
            return $overLimit;
        }

        $reference = $this->wireReference($request->reference);
        $currency = strtoupper($request->currency);

        $recipient = $this->recipientPayload($request, $currency);

        try {
            $recipientResponse = $this->client()->post('/transferrecipient', $recipient);

            $recipientData = (array) $recipientResponse->json('data', []);
            $recipientCode = (string) ($recipientData['recipient_code'] ?? '');

            // No recipient, no transfer: this step is definitive (nothing has been sent yet).
            if (! $this->succeeded($recipientResponse) || $recipientCode === '') {
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    amount: round($request->amount, 2),
                    currency: $currency,
                    reference: $request->reference,
                    message: $this->messageFrom($recipientResponse, 'Paystack could not prepare a recipient for that account — nothing was sent.'),
                    data: ['recipient_request' => $recipient, 'response' => $recipientData, 'http_status' => $recipientResponse->status()],
                );
            }

            $payload = array_filter([
                'source' => 'balance',
                'amount' => $this->toSubunit($request->amount),
                'recipient' => $recipientCode,
                'reference' => $reference,
                'reason' => $request->narration,
                'currency' => $currency,
            ], fn ($value): bool => $value !== null && $value !== '');

            $response = $this->client()->post('/transfer', $payload);
            $data = (array) $response->json('data', []);

            // 5xx/timeout: Paystack may have queued the transfer — report UNKNOWN so the admin
            // verifies by reference (rather than re-sending money that may already be on its way).
            if ($response->serverError() || $response->json('status') === null) {
                return PayoutResult::unknown(
                    $this->messageFrom($response, 'Paystack did not answer definitively — verify the reference before retrying.'),
                    ['payload' => $payload, 'response' => $data, 'http_status' => $response->status(), 'recipient' => $recipientCode],
                );
            }

            if (! $this->succeeded($response)) {
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    amount: $this->amountFrom($data, $request->amount),
                    currency: (string) ($data['currency'] ?? $currency),
                    reference: (string) ($data['reference'] ?? $request->reference),
                    gatewayId: filled($data['transfer_code'] ?? null) ? (string) $data['transfer_code'] : null,
                    message: $this->messageFrom($response, 'Paystack rejected the payout request.'),
                    data: ['payload' => $payload, 'response' => $data, 'http_status' => $response->status(), 'recipient' => $recipientCode],
                );
            }

            $status = $this->normaliseStatus((string) ($data['status'] ?? 'pending'), authoritative: false);

            return new PayoutResult(
                ok: true,
                status: $status,
                amount: $this->amountFrom($data, $request->amount),
                fee: round($this->toMajor((float) ($data['fee_charged'] ?? 0)), 2),
                currency: (string) ($data['currency'] ?? $currency),
                reference: (string) ($data['reference'] ?? $request->reference),
                gatewayId: filled($data['transfer_code'] ?? null) ? (string) $data['transfer_code'] : null,
                message: $this->transferMessage($data, $response, $status),
                data: ['payload' => $payload, 'response' => $data, 'http_status' => $response->status(), 'recipient' => $recipientCode, 'recipient_request' => $recipient],
            );
        } catch (Throwable $e) {
            $this->failed($e, 'payout disburse');

            return PayoutResult::unknown('The payout request could not be completed — verify the reference before retrying.');
        }
    }

    /**
     * The transfer-recipient payload for a payout request. `type` follows the payout currency
     * (Paystack pays Ghana through ghipss, Nigeria through nuban).
     *
     * @return array<string, mixed>
     */
    private function recipientPayload(PayoutRequest $request, string $currency): array
    {
        return array_filter([
            'type' => self::RECIPIENT_TYPES[$currency] ?? 'nuban',
            'name' => $request->accountName ?: ($request->customerName ?: 'Nextel payout'),
            'account_number' => $request->accountNumber,
            'bank_code' => $request->bankCode,
            'currency' => $currency,
            'description' => $request->narration,
        ], fn ($value): bool => $value !== null && $value !== '');
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
     * The authoritative transfer status.
     *
     * Accepts OUR reference, or Paystack's own transfer code/id for a payout that was checked from
     * the Paystack dashboard: a `TRF_…` code or a numeric id is fetched directly, anything else is
     * verified by reference.
     */
    public function verifyPayout(string $reference): PayoutResult
    {
        if (! $this->configured()) {
            return PayoutResult::unknown('Paystack is not configured — add the secret key under Settings → Services.');
        }

        $needle = trim($reference);

        if ($needle === '') {
            return PayoutResult::unknown('Enter a payout reference to verify.');
        }

        try {
            // Paystack's own handle, pasted from the dashboard.
            if (ctype_digit($needle) || str_starts_with(strtoupper($needle), 'TRF_')) {
                $response = $this->client()->get('/transfer/'.rawurlencode($needle));
            } else {
                $response = $this->client()->get('/transfer/verify/'.rawurlencode($this->wireReference($needle)));
            }

            $data = (array) $response->json('data', []);

            // A transfer that does not exist is a definitive "not found", not a transport problem.
            if ($response->status() === 404) {
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    reference: $needle,
                    message: $this->messageFrom($response, 'Paystack has no transfer with that reference.'),
                    data: ['response' => $data, 'http_status' => 404],
                );
            }

            if ($response->serverError() || $response->status() === 0) {
                return PayoutResult::unknown('Paystack could not be reached — the payout status is unknown, try again.', ['http_status' => $response->status()]);
            }

            if (! $this->succeeded($response)) {
                return PayoutResult::unknown(
                    $this->messageFrom($response, 'Paystack rejected the payout verification request.'),
                    ['response' => $data, 'http_status' => $response->status()],
                );
            }

            if ($data === []) {
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    reference: $needle,
                    message: 'Paystack has no transfer with that reference.',
                    data: ['response' => $data, 'http_status' => $response->status()],
                );
            }

            $status = $this->normaliseStatus((string) ($data['status'] ?? ''), authoritative: true);

            return new PayoutResult(
                ok: true,
                status: $status,
                amount: $this->toMajor((float) ($data['amount'] ?? 0)),
                fee: $this->toMajor((float) ($data['fee_charged'] ?? 0)),
                currency: strtoupper((string) ($data['currency'] ?? '')),
                reference: (string) ($data['reference'] ?? $needle),
                gatewayId: filled($data['transfer_code'] ?? null) ? (string) $data['transfer_code'] : null,
                message: $this->transferMessage($data, $response, $status),
                data: $data,
            );
        } catch (Throwable $e) {
            $this->failed($e, 'payout verify');

            return PayoutResult::unknown('Paystack could not be reached — the payout status is unknown, try again.');
        }
    }

    /**
     * Resolve the account holder's name for a destination bank account before paying out.
     *
     * @return array{ok: bool, account_name: ?string, bank_name: ?string, message: ?string}
     */
    public function resolveBankAccount(string $bankCode, string $accountNumber, string $currency = 'NGN'): array
    {
        $denied = ['ok' => false, 'account_name' => null, 'bank_name' => null, 'message' => null];

        if (! $this->configured()) {
            return [...$denied, 'message' => 'Paystack is not configured.'];
        }

        try {
            $response = $this->client()->get('/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

            if (! $this->succeeded($response)) {
                return [...$denied, 'message' => $this->messageFrom($response, 'That account could not be resolved.')];
            }

            return [
                'ok' => true,
                'account_name' => filled($response->json('data.account_name')) ? (string) $response->json('data.account_name') : null,
                // Paystack's resolve answer carries no bank name; the payout form shows our own
                // Bank record for the chosen code instead.
                'bank_name' => null,
                'message' => null,
            ];
        } catch (Throwable $e) {
            $this->failed($e, 'account resolve');

            return [...$denied, 'message' => 'The account resolution request could not be completed.'];
        }
    }

    /* ------------------------------------------------------------------------------- helpers */

    /**
     * Paystack transfer statuses collapsed onto the states the app stores.
     *
     * `authoritative` separates the two moments Paystack uses the same word for different things:
     * an INITIATED transfer answers `success` as soon as Paystack has queued it (the transfer still
     * has to settle), while the VERIFY endpoint's `success` is the real, final one.
     */
    public function normaliseStatus(string $status, bool $authoritative = true): string
    {
        return match (strtolower(trim($status))) {
            'success' => $authoritative ? PayoutResult::STATUS_SUCCESS : PayoutResult::STATUS_PROCESSING,
            'failed', 'reversed', 'abandoned', 'declined' => PayoutResult::STATUS_FAILED,
            // `otp` means Paystack has held the transfer until an OTP is approved: it is NOT on its
            // way yet, so it must never look final (or successful) in the backoffice.
            'otp', 'pending' => PayoutResult::STATUS_PENDING,
            'processing', 'queued', 'initiated', 'in_progress' => PayoutResult::STATUS_PROCESSING,
            default => PayoutResult::STATUS_PENDING,
        };
    }

    /**
     * The most useful sentence about a transfer: Paystack puts the reason in `message` at request
     * level and leaves `failures`/`gateway_response` on the transfer itself.
     *
     * @param  array<string, mixed>  $data
     */
    private function transferMessage(array $data, Response $response, string $status): ?string
    {
        foreach ([$response->json('message'), $data['gateway_response'] ?? null, $data['failures'] ?? null] as $candidate) {
            if (is_string($candidate) && filled($candidate)) {
                return $candidate;
            }
        }

        if ($status === PayoutResult::STATUS_PENDING && strtolower((string) ($data['status'] ?? '')) === 'otp') {
            return 'Paystack is holding this transfer until its OTP is approved — approve it on the Paystack dashboard to let it leave.';
        }

        return null;
    }

    /**
     * Our reference mapped onto a Paystack-legal one: 16–50 characters of lowercase letters,
     * digits, dashes and underscores. Deterministic, so `verifyPayout()` can rebuild it from the
     * same reference without keeping a second copy anywhere.
     */
    public function wireReference(string $reference): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9\-_]/', '-', trim($reference)));

        if ($slug === '') {
            $slug = 'payout';
        }

        // Shorter than Paystack's floor: pad with a deterministic slice of the reference's own hash,
        // which also keeps two different short references from colliding on the same padded value.
        if (strlen($slug) < self::WIRE_REFERENCE_MIN) {
            $slug = substr($slug.'-'.md5($slug), 0, self::WIRE_REFERENCE_MIN);
        }

        return substr($slug, 0, self::WIRE_REFERENCE_MAX);
    }

    /**
     * An amount out of a Paystack response body: the figure it quotes (in subunits) when it sent
     * one, otherwise the amount we asked for — never zero just because the body was thin.
     *
     * @param  array<string, mixed>  $data
     */
    private function amountFrom(array $data, float $fallback): float
    {
        $quoted = (float) ($data['amount'] ?? 0);

        return $quoted > 0 ? $this->toMajor($quoted) : round($fallback, 2);
    }

    /** Subunits (kobo/pesewas) → major units, for anything Paystack quotes in integers. */
    private function toMajor(float $subunit): float
    {
        return round($subunit / 100, 2);
    }

    /** Major units → subunits, the only way Paystack accepts an amount. */
    private function toSubunit(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
