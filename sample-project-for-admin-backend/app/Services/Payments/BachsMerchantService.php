<?php

namespace App\Services\Payments;

use App\Services\Payments\DTO\MerchantApiResult;
use App\Services\Payments\Gateways\BachsGateway;
use App\Services\Payments\DTO\PayoutRequest;
use App\Services\Payments\DTO\PayoutResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bachs MERCHANT operations for the backoffice: balance, wallet statement (history), single payouts
 * and payout verification. Deliberately separate from BachsGateway — that driver COLLECTS charges
 * through the PaymentGateway contract, while everything here moves money OUT of the Bachs balance and
 * is only reachable from the backoffice.
 *
 * Endpoints (all authenticated with the secret key as a Bearer token):
 *   GET  /v1/balances                       → available/pending per currency (+ USD total)
 *   GET  /v1/payments                       → collections in (a page of the wallet statement)
 *   GET  /v1/payouts                        → payouts out (the other half of the statement)
 *   GET  /v1/payouts/destinations           → saved destinations, matched before creating one
 *   POST /v1/payouts/destinations           → register the bank account being paid
 *   POST /v1/payouts                        → the payout itself (Idempotency-Key = our reference)
 *   GET  /v1/payouts/{id}                   → authoritative payout status by Bachs' own id
 *   POST /v1/misc/bank-accounts/resolve     → account holder name before paying out
 *
 * Amounts are decimal STRINGS in the major unit ("9000.00") — Bachs never uses minor units, so there
 * is no subunit arithmetic anywhere in this class.
 *
 * Nothing here throws on an API/transport error: results carry `ok` so the UI shows Bachs' own
 * message, and a payout that errored mid-flight is never mistaken for a failed payout (see
 * verifyPayout() — verify before retrying).
 *
 * Honest limitation: Bachs exposes no balance-ledger endpoint, so the statement below is built from
 * the two things that move a balance (collections and payouts). That means it cannot show a running
 * balance per row (`balance_before`/`balance_after` stay null and the page renders "—"), and refunds,
 * conversions, transfers and disputes are not line items here.
 */
class BachsMerchantService implements MerchantWalletService
{
    public function name(): string
    {
        return 'bachs';
    }

    public function configured(): bool
    {
        return filled(config('services.bachs.secret_key'));
    }

    /** Sandbox is decided by the key prefix (sk_sandbox_…) or a sandbox base URL — shown as a badge. */
    public function isTestMode(): bool
    {
        $key = (string) config('services.bachs.secret_key');
        $baseUrl = strtolower((string) config('services.bachs.base_url'));

        return str_starts_with($key, 'sk_sandbox') || str_contains($baseUrl, 'sandbox');
    }

    /**
     * Currencies the backoffice may pay out in (config-driven). Bachs funds a payout from the balance
     * in the destination's own currency for a same-currency payout, so this is also the wallet list.
     *
     * @return list<string>
     */
    public function payoutCurrencies(): array
    {
        $configured = config('services.bachs.payout_currencies', ['NGN']);

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code): string => strtoupper(trim((string) $code)),
            is_array($configured) ? $configured : explode(',', (string) $configured),
        ))));

        return $codes === [] ? ['NGN'] : $codes;
    }

    /** Per-payout ceiling from config; 0 or less disables the check. */
    public function maxPayoutAmount(): float
    {
        return (float) config('services.bachs.payout_max_amount', 1000000);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.bachs.base_url', 'https://api.bachs.io'))
            ->withToken((string) config('services.bachs.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /* ------------------------------------------------------------------------------- balances */

    /**
     * Balance per currency: `[['currency' => 'NGN', 'available' => 58700.0, 'pending' => 0.0, 'issuing' => null], …]`.
     *
     * Bachs reports `available_balance` and `pending_balance` per currency bucket; `issuing` stays null
     * (the key exists so the shared balance UI needs no special casing).
     */
    public function balances(): MerchantApiResult
    {
        if (! $this->configured()) {
            return MerchantApiResult::failure('Bachs is not configured — add the secret key under Settings → Services.');
        }

        try {
            $response = $this->client()->get('/v1/balances');

            if ($response->failed() || $response->serverError()) {
                return MerchantApiResult::failure(self::messageFrom($response, 'Bachs rejected the balance request.'));
            }

            $balances = [];

            foreach ((array) $response->json('balances', []) as $row) {
                if (! is_array($row) || blank($row['currency'] ?? null)) {
                    continue;
                }

                $balances[] = [
                    'currency' => strtoupper((string) $row['currency']),
                    'available' => round((float) ($row['available_balance'] ?? 0), 2),
                    'pending' => round((float) ($row['pending_balance'] ?? 0), 2),
                    'issuing' => null,
                ];
            }

            if ($balances === []) {
                return MerchantApiResult::failure('Bachs returned no balance for this account.');
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

            return MerchantApiResult::failure('The Bachs balance request could not be completed.');
        }
    }

    /* --------------------------------------------------------------------- balance history */

    /**
     * Wallet statement — the money in (collections) and out (payouts) that moved the balance.
     *
     * Bachs has no ledger endpoint, so a page is one page of `GET /v1/payments` merged with one page of
     * `GET /v1/payouts`, each source newest-first: a `pointer` is therefore the page number both were
     * read at, and `has_more` is true while EITHER source has more. A payout that finally failed is
     * excluded (Bachs returns the funds); a payout still in flight is included as a debit because it
     * already left the available balance.
     *
     * The documented filters are re-applied here, so a filtered page only ever shows rows that match.
     *
     * @param  array{currency?: ?string, direction?: ?string, date_from?: ?string, date_to?: ?string, limit?: ?int, page?: ?int, starting_after?: ?string, ending_before?: ?string}  $filters
     * @return array{ok: bool, message: ?string, entries: list<array<string, mixed>>, has_more: bool, pointers: array<string, ?string>}
     */
    public function balanceHistory(array $filters = []): array
    {
        $empty = ['ok' => false, 'message' => null, 'entries' => [], 'has_more' => false, 'pointers' => ['first' => null, 'last' => null]];

        if (! $this->configured()) {
            return [...$empty, 'message' => 'Bachs is not configured — add the secret key under Settings → Services.'];
        }

        $page = max(1, (int) ($filters['starting_after'] ?? $filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        try {
            $payments = $this->client()->get('/v1/payments', ['limit' => $perPage, 'offset' => $offset]);
            $payouts = $this->client()->get('/v1/payouts', ['limit' => $perPage, 'offset' => $offset]);

            if ($payments->failed() || $payouts->failed() || $payments->serverError() || $payouts->serverError()) {
                return [...$empty, 'message' => self::messageFrom($payments->failed() ? $payments : $payouts, 'Bachs rejected the wallet statement request.')];
            }

            $entries = [
                ...$this->collectionEntries((array) $payments->json('items', []), $page),
                ...$this->payoutEntries((array) $payouts->json('items', []), $page),
            ];

            $entries = $this->filterLedgerRows($entries, $filters);

            usort($entries, fn (array $a, array $b): int => strcmp((string) $b['date_created'], (string) $a['date_created']));

            return [
                'ok' => true,
                'message' => null,
                'entries' => array_values($entries),
                'has_more' => (bool) ($payments->json('pagination.has_more') || $payouts->json('pagination.has_more')),
                'pointers' => [
                    'first' => '1',
                    'last' => (string) $page,
                ],
            ];
        } catch (Throwable $e) {
            $this->failed($e, 'balance statement');

            return [...$empty, 'message' => 'The Bachs wallet statement request could not be completed.'];
        }
    }

    /**
     * Collections as credit entries. Only statuses that actually moved money in are included: a
     * failed or expired collection never reached the balance.
     *
     * @param  array<array-key, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function collectionEntries(array $items, int $page): array
    {
        $moneyIn = ['succeeded', 'accepted', 'overpaid', 'underpaid'];

        $entries = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! in_array(strtolower((string) ($item['status'] ?? '')), $moneyIn, true)) {
                continue;
            }

            $reference = (string) ($item['reference'] ?? $item['id'] ?? '');

            $entries[] = [
                'pointer' => (string) $page, // both sources are read at the same page number
                'amount' => round((float) ($item['amount_paid'] ?? $item['amount'] ?? 0), 2),
                'currency' => strtoupper((string) ($item['currency'] ?? '')),
                'balance_before' => null, // Bachs exposes no running balance (see the class docblock)
                'balance_after' => null,
                'date_created' => (string) ($item['transaction_date'] ?? $item['completed_at'] ?? ''),
                'description' => 'Collection'.($reference !== '' ? ' · '.$reference : ''),
                'direction' => 'credit',
                'source' => 'payment',
                'source_reference' => $reference,
                'transaction_type' => strtolower((string) ($item['status'] ?? '')),
            ];
        }

        return $entries;
    }

    /**
     * Payouts as debit entries. `total_debited` is what left the balance (in `source_currency`) and is
     * preferred over the destination amount, which is a different currency on a converted payout.
     *
     * @param  array<array-key, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function payoutEntries(array $items, int $page): array
    {
        $entries = [];

        foreach ($items as $item) {
            if (! is_array($item) || strtolower((string) ($item['status'] ?? '')) === 'failed') {
                continue;
            }

            $reference = (string) ($item['reference'] ?? $item['id'] ?? '');

            $entries[] = [
                'pointer' => (string) $page,
                'amount' => round((float) ($item['total_debited'] ?? $item['amount'] ?? 0), 2),
                'currency' => strtoupper((string) ($item['source_currency'] ?? $item['currency'] ?? '')),
                'balance_before' => null,
                'balance_after' => null,
                'date_created' => (string) ($item['created_at'] ?? ''),
                'description' => 'Payout'.($reference !== '' ? ' · '.$reference : ''),
                'direction' => 'debit',
                'source' => 'payout',
                'source_reference' => $reference,
                'transaction_type' => strtolower((string) ($item['status'] ?? '')),
            ];
        }

        return $entries;
    }

    /**
     * Apply the statement filters locally, so the page never shows rows the admin filtered out.
     *
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function filterLedgerRows(array $entries, array $filters): array
    {
        $currency = strtoupper(trim((string) ($filters['currency'] ?? '')));
        $direction = strtolower(trim((string) ($filters['direction'] ?? '')));

        // The page sends `Y-m-d-00-00-00` style strings; only the date matters here.
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
     * Request a single payout to a bank account.
     *
     * Bachs pays a DESTINATION, so the account is first matched against (or registered as) one — a
     * repeat payout to an account the account holder already saved reuses it instead of piling up
     * duplicates. Only then is the payout created, with our reference as both `reference` and
     * `Idempotency-Key` so a repeated request cannot create two payouts.
     *
     * A transport/5xx failure returns `ok: false` with `unknown` — Bachs may still have accepted the
     * payout, so the caller MUST verify by reference instead of retrying blindly.
     */
    public function disburse(PayoutRequest $request): PayoutResult
    {
        if (! $this->configured()) {
            return PayoutResult::unknown('Bachs is not configured — add the secret key under Settings → Services.');
        }

        $overLimit = $this->overPayoutLimit($request);

        if ($overLimit !== null) {
            return $overLimit;
        }

        $currency = strtoupper($request->currency);

        try {
            $destination = $this->destinationFor($request, $currency);

            if (is_string($destination)) {
                // A definitive refusal from the destination step: nothing has been sent.
                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    amount: round($request->amount, 2),
                    currency: $currency,
                    reference: $request->reference,
                    message: $destination,
                );
            }

            $payload = array_filter([
                'destination' => $destination['id'],
                'amount' => BachsGateway::money($request->amount),
                'reference' => $request->reference,
                'metadata' => array_merge($request->metadata, ['narration' => $request->narration]),
            ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);

            $response = $this->client()
                ->withHeaders(['Idempotency-Key' => $request->reference])
                ->post('/v1/payouts', $payload);

            $data = (array) $response->json();

            // 5xx/timeout: Bachs may have accepted the payout — report UNKNOWN so it is verified
            // by reference, never re-sent.
            if ($response->serverError() || $response->status() === 0) {
                return PayoutResult::unknown(
                    self::messageFrom($response, 'Bachs did not answer definitively — verify the reference before retrying.'),
                    ['payload' => $payload, 'response' => $data, 'http_status' => $response->status(), 'destination' => $destination['id']],
                );
            }

            if ($response->failed()) {
                // 4xx: Bachs refused the payout. A rate limit (429) is the one refusal that says
                // nothing about the payout, so it must stay unknown instead of failed.
                $message = self::messageFrom($response, 'Bachs rejected the payout request.');

                if ($response->status() === 429) {
                    return PayoutResult::unknown($message, ['payload' => $payload, 'response' => $data, 'http_status' => 429, 'destination' => $destination['id']]);
                }

                return new PayoutResult(
                    ok: true,
                    status: PayoutResult::STATUS_FAILED,
                    amount: round($request->amount, 2),
                    currency: $currency,
                    reference: $request->reference,
                    message: $message,
                    data: ['payload' => $payload, 'response' => $data, 'http_status' => $response->status(), 'destination' => $destination['id']],
                );
            }

            $status = self::normalisePayoutStatus((string) ($data['status'] ?? ''));

            return new PayoutResult(
                ok: true,
                status: $status,
                amount: round((float) ($data['amount'] ?? $request->amount), 2),
                fee: round((float) ($data['fee'] ?? 0), 2),
                currency: strtoupper((string) ($data['currency'] ?? $currency)),
                reference: (string) ($data['reference'] ?? $request->reference),
                gatewayId: filled($data['id'] ?? null) ? (string) $data['id'] : null,
                message: filled($data['failure_reason'] ?? null) ? (string) $data['failure_reason'] : null,
                data: ['payload' => $payload, 'response' => $data, 'http_status' => $response->status(), 'destination' => $destination['id'], 'destination_record' => $destination],
            );
        } catch (Throwable $e) {
            $this->failed($e, 'payout disburse');

            return PayoutResult::unknown('The payout request could not be completed — verify the reference before retrying.', [
                'exception' => $e::class,
                'at' => $e->getFile().':'.$e->getLine(),
                'why' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Match the account against a saved destination, or register it.
     *
     * Returns the destination array, or a definitive refusal MESSAGE (string) when nothing was sent.
     *
     * @return array{id: string, is_usable: bool, account_name: ?string, bank_name: ?string, reused: bool}|string
     */
    private function destinationFor(PayoutRequest $request, string $currency): array|string
    {
        $existing = $this->client()->get('/v1/payouts/destinations', ['limit' => 100]);

        if ($existing->failed() || $existing->serverError()) {
            return self::messageFrom($existing, 'Bachs could not be asked for the saved payout destinations — nothing was sent.');
        }

        // A failed list call must not fall through to creating a second destination for an account
        // that already has one, so the lookup failing is a refusal, not a "create another".
        foreach ((array) $existing->json('items', []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $matches = strtoupper((string) ($candidate['currency'] ?? '')) === $currency
                && (string) ($candidate['bank_code'] ?? '') === $request->bankCode
                && (string) ($candidate['account_number'] ?? '') === $request->accountNumber;

            if (! $matches) {
                continue;
            }

            if (! ($candidate['is_usable'] ?? false)) {
                return 'This account is saved as a Bachs payout destination but is not usable yet ('.((string) ($candidate['status'] ?? 'pending_review')).(filled($candidate['status_reason'] ?? null) ? ': '.$candidate['status_reason'] : '').'). Nothing was sent.';
            }

            return [
                'id' => (string) $candidate['id'],
                'is_usable' => true,
                'account_name' => filled($candidate['account_name'] ?? null) ? (string) $candidate['account_name'] : null,
                'bank_name' => filled($candidate['bank_name'] ?? null) ? (string) $candidate['bank_name'] : null,
                'reused' => true,
            ];
        }

        $created = $this->client()->post('/v1/payouts/destinations', array_filter([
            'type' => 'bank_account',
            'currency' => $currency,
            'bank_code' => $request->bankCode,
            'account_number' => $request->accountNumber,
            'name' => $request->bankName ?: null,
        ], fn ($value): bool => $value !== null && $value !== ''));

        if ($created->failed() || $created->serverError()) {
            return self::messageFrom($created, 'Bachs could not register that account as a payout destination — nothing was sent.');
        }

        $destination = (array) $created->json();

        if (blank($destination['id'] ?? null)) {
            return 'Bachs did not return a payout destination for that account — nothing was sent.';
        }

        if (! ($destination['is_usable'] ?? false)) {
            return 'Bachs registered the account, but the destination is not usable yet ('.((string) ($destination['status'] ?? 'pending_review')).(filled($destination['status_reason'] ?? null) ? ': '.$destination['status_reason'] : '').'). Nothing was sent — retry once it is approved.';
        }

        return [
            'id' => (string) $destination['id'],
            'is_usable' => true,
            'account_name' => filled($destination['account_name'] ?? null) ? (string) $destination['account_name'] : null,
            'bank_name' => filled($destination['bank_name'] ?? null) ? (string) $destination['bank_name'] : null,
            'reused' => false,
        ];
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
     * The authoritative payout status.
     *
     * Accepts OUR reference, or Bachs' own payout id (`pay_…`) for a payout checked from the Bachs
     * dashboard: an id is fetched directly, anything else is looked up by scanning the newest payouts
     * (Bachs has no get-by-reference endpoint — bounded by `services.bachs.lookup_scan_pages`).
     */
    public function verifyPayout(string $reference): PayoutResult
    {
        if (! $this->configured()) {
            return PayoutResult::unknown('Bachs is not configured — add the secret key under Settings → Services.');
        }

        $needle = trim($reference);

        if ($needle === '') {
            return PayoutResult::unknown('Enter a payout reference to verify.');
        }

        try {
            if (str_starts_with($needle, 'pay_')) {
                return $this->payoutFrom($this->client()->get('/v1/payouts/'.rawurlencode($needle)), $needle);
            }

            $pages = max(1, (int) config('services.bachs.lookup_scan_pages', 2));

            for ($page = 0; $page < $pages; $page++) {
                $response = $this->client()->get('/v1/payouts', ['limit' => 100, 'offset' => $page * 100]);

                if ($response->failed() || $response->serverError()) {
                    return PayoutResult::unknown(self::messageFrom($response, 'Bachs could not be reached — the payout status is unknown, try again.'));
                }

                foreach ((array) $response->json('items', []) as $item) {
                    if (! is_array($item) || (string) ($item['reference'] ?? '') !== $needle) {
                        continue;
                    }

                    return new PayoutResult(
                        ok: true,
                        status: self::normalisePayoutStatus((string) ($item['status'] ?? '')),
                        amount: round((float) ($item['amount'] ?? 0), 2),
                        fee: round((float) ($item['fee'] ?? 0), 2),
                        currency: strtoupper((string) ($item['currency'] ?? '')),
                        reference: (string) ($item['reference'] ?? $needle),
                        gatewayId: filled($item['id'] ?? null) ? (string) $item['id'] : null,
                        message: filled($item['failure_reason'] ?? null) ? (string) $item['failure_reason'] : null,
                        data: $item,
                    );
                }

                if ((array) $response->json('items', []) === [] || ! (bool) $response->json('pagination.has_more', false)) {
                    break;
                }
            }

            return new PayoutResult(
                ok: true,
                status: PayoutResult::STATUS_FAILED,
                reference: $needle,
                message: 'Bachs has no payout with that reference.',
            );
        } catch (Throwable $e) {
            $this->failed($e, 'payout verify');

            return PayoutResult::unknown('Bachs could not be reached — the payout status is unknown, try again.');
        }
    }

    /** Turn one payout GET response into a PayoutResult (404 = definitively not a Bachs payout). */
    private function payoutFrom(Response $response, string $needle): PayoutResult
    {
        if ($response->status() === 404) {
            return new PayoutResult(
                ok: true,
                status: PayoutResult::STATUS_FAILED,
                reference: $needle,
                message: 'Bachs has no payout with that id.',
            );
        }

        if ($response->failed() || $response->serverError()) {
            return PayoutResult::unknown(self::messageFrom($response, 'Bachs could not be reached — the payout status is unknown, try again.'));
        }

        $data = (array) $response->json();

        if ($data === []) {
            return new PayoutResult(
                ok: true,
                status: PayoutResult::STATUS_FAILED,
                reference: $needle,
                message: 'Bachs has no payout with that id.',
            );
        }

        return new PayoutResult(
            ok: true,
            status: self::normalisePayoutStatus((string) ($data['status'] ?? '')),
            amount: round((float) ($data['amount'] ?? 0), 2),
            fee: round((float) ($data['fee'] ?? 0), 2),
            currency: strtoupper((string) ($data['currency'] ?? '')),
            reference: (string) ($data['reference'] ?? $needle),
            gatewayId: filled($data['id'] ?? null) ? (string) $data['id'] : null,
            message: filled($data['failure_reason'] ?? null) ? (string) $data['failure_reason'] : null,
            data: $data,
        );
    }

    /**
     * Bachs' payout statuses onto the states the app stores. Unlike Paystack there is no
     * "accepted ≠ delivered" trap here: `pending` and `processing` are in flight, `completed` is
     * delivery and `failed` is final.
     */
    public static function normalisePayoutStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'completed', 'success', 'succeeded', 'paid' => PayoutResult::STATUS_SUCCESS,
            'failed', 'rejected', 'reversed' => PayoutResult::STATUS_FAILED,
            'processing' => PayoutResult::STATUS_PROCESSING,
            'pending' => PayoutResult::STATUS_PENDING,
            default => PayoutResult::STATUS_PENDING,
        };
    }

    /**
     * Resolve the account holder's name for a destination bank account before paying out.
     *
     * `resolved: false` is not an HTTP error in Bachs — it covers a wrong number, an unsupported
     * country and a lookup that could not complete, with `message` explaining which.
     *
     * @return array{ok: bool, account_name: ?string, bank_name: ?string, message: ?string}
     */
    public function resolveBankAccount(string $bankCode, string $accountNumber, string $currency = 'NGN'): array
    {
        $denied = ['ok' => false, 'account_name' => null, 'bank_name' => null, 'message' => null];

        if (! $this->configured()) {
            return [...$denied, 'message' => 'Bachs is not configured.'];
        }

        try {
            $response = $this->client()->post('/v1/misc/bank-accounts/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'country' => self::countryFor($currency),
            ]);

            if ($response->failed() || $response->serverError()) {
                return [...$denied, 'message' => self::messageFrom($response, 'That account could not be resolved.')];
            }

            if (! $response->json('resolved')) {
                return [...$denied, 'message' => (string) ($response->json('message') ?: 'That account could not be resolved.')];
            }

            return [
                'ok' => true,
                'account_name' => filled($response->json('account_name')) ? (string) $response->json('account_name') : null,
                // Bachs' resolve answer carries no bank name; the payout form shows our own Bank
                // record for the chosen code instead.
                'bank_name' => null,
                'message' => null,
            ];
        } catch (Throwable $e) {
            $this->failed($e, 'account resolve');

            return [...$denied, 'message' => 'The account resolution request could not be completed.'];
        }
    }

    /** The country a payout currency resolves in (NGN → NG); configurable fallback. */
    public static function countryFor(string $currency): string
    {
        return match (strtoupper($currency)) {
            'GHS' => 'GH',
            'KES' => 'KE',
            'ZAR' => 'ZA',
            default => strtoupper((string) config('services.bachs.default_country', 'NG')),
        };
    }

    /* ------------------------------------------------------------------------------- helpers */

    /** Never surface a raw transport exception to a backoffice page — but do log it. */
    private function failed(Throwable $e, string $context): void
    {
        Log::warning('Bachs merchant request failed', ['context' => $context, 'message' => $e->getMessage()]);
    }

    private static function messageFrom(Response $response, string $fallback): string
    {
        foreach ([$response->json('detail'), $response->json('message'), $response->json('error_code')] as $candidate) {
            if (is_string($candidate) && filled($candidate)) {
                return $candidate;
            }
        }

        return $fallback;
    }
}
