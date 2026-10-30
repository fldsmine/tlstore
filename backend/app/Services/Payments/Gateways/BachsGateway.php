<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\Transaction;
use App\Services\Payments\DTO\ChargeRequest;
use App\Services\Payments\DTO\InitiateResult;
use App\Services\Payments\DTO\VerifyResult;
use App\Services\Payments\DTO\WebhookEvent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bachs (bachs.io) as a CHARGE provider: a hosted checkout session per purchase, confirmed
 * server-side before anything is credited.
 *
 * Contract notes that shape this driver (docs.bachs.io):
 *  - Base URLs: https://api.bachs.io (live) and https://sandbox-api.bachs.io (sandbox), selected with
 *    an `sk_live_…` / `sk_sandbox_…` Bearer key.
 *  - Money is a DECIMAL STRING in the currency's MAJOR unit — `"2500.00"` for ₦2,500.00. Bachs never
 *    uses minor units, so unlike Paystack there is no ×100 conversion here.
 *  - A session carries exactly ONE pricing source: `product_cart` (catalog items) or `pricing` (a raw
 *    amount). Our five purchases are computed at order time, so this driver always sends `pricing`.
 *  - `reference` is OUR reference, echoed back on the session and on every payment for it — that echo
 *    is the reconciliation key, so a session started for one charge can never settle another.
 *  - `checkout.completed` means the CUSTOMER FINISHED THE PAGE, not that money was collected; only
 *    `collection.succeeded` (or the payment status) says that. Nothing here treats a completed
 *    checkout as paid.
 */
class BachsGateway implements PaymentGateway
{
    /** Event types this driver will turn into a charge event. */
    private const CHARGE_EVENTS = ['collection.succeeded', 'collection.failed', 'collection.underpaid', 'checkout.completed', 'checkout.expired'];

    public function name(): string
    {
        return 'bachs';
    }

    public function configured(): bool
    {
        return filled(config('services.bachs.secret_key'));
    }

    public function chargeCurrency(): string
    {
        return strtoupper((string) config('services.bachs.currency', 'NGN'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.bachs.base_url', 'https://api.bachs.io'))
            ->withToken((string) config('services.bachs.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /**
     * Start a hosted checkout for a raw amount. The reference is also sent as the `Idempotency-Key`
     * header: Bachs caches the successful response for a repeated key, so a double-tapped pay button
     * cannot mint two sessions for one charge.
     */
    public function initiate(ChargeRequest $request): InitiateResult
    {
        if (! $this->configured()) {
            return InitiateResult::failure('Bachs is not configured — add the secret key under Settings → Services.');
        }

        try {
            $payload = array_filter([
                'pricing' => [
                    'currency' => $request->currency,
                    'amount' => self::money($request->amount),
                ],
                'reference' => $request->reference,
                'customer' => array_filter([
                    'email' => $request->email,
                    'name' => $request->customerName ?: null,
                ]),
                'success_url' => $request->callbackUrl,
                'cancel_url' => $request->callbackUrl,
                'expires_in_minutes' => (int) config('services.bachs.checkout_expiry_minutes', 60),
                'metadata' => array_merge($request->metadata, ['reference' => $request->reference]),
                'payment_method_types' => self::paymentMethods(),
            ], fn ($value): bool => $value !== null && $value !== []);

            $response = $this->client()
                ->withHeaders(['Idempotency-Key' => $request->reference])
                ->post('/v1/checkout-sessions', $payload);

            $url = (string) $response->json('checkout_url', '');
            $id = (string) $response->json('checkout_id', '');

            if ($response->failed() || $url === '' || $id === '') {
                Log::warning('Bachs checkout initialization failed', [
                    'reference' => $request->reference,
                    'http_status' => $response->status(),
                    'error_code' => $response->json('error_code'),
                ]);

                return InitiateResult::failure(self::messageFrom($response, 'Payment initialization failed'));
            }

            return InitiateResult::success($url, $id);
        } catch (Throwable $e) {
            Log::warning('Bachs checkout initialization errored', ['reference' => $request->reference, 'message' => $e->getMessage()]);

            return InitiateResult::failure('Unable to initialize payment');
        }
    }

    /**
     * Authoritatively confirm a charge, by OUR reference.
     *
     * Primary path: the checkout id recorded when the charge started is retrieved
     * (`GET /v1/checkout-sessions/{checkout_id}`), which echoes our reference and carries the charge's
     * amount, currency and status. Bachs has no endpoint to look a payment up BY reference, so when no
     * checkout id was recorded the newest payments are scanned for our reference (bounded by
     * `services.bachs.lookup_scan_pages`) — best-effort, and a miss is reported as "could not answer"
     * rather than as a failed charge.
     *
     * Anything that is not a definitive answer (transport error, non-2xx, a 404 for a checkout id we
     * were given, a scan that finds nothing) returns `unavailable()`: the charge stays pending for the
     * requery sweep, so a Bachs outage or a mispointed base URL can never fail a charge that was paid.
     */
    public function verify(string $reference): VerifyResult
    {
        if (! $this->configured()) {
            return VerifyResult::unavailable();
        }

        try {
            $checkoutId = (string) (Transaction::query()->where('reference', $reference)->value('gateway_reference') ?? '');

            if (str_starts_with($checkoutId, 'chk_')) {
                return $this->verifyFromCheckout($reference, $checkoutId);
            }

            return $this->verifyFromPaymentScan($reference);
        } catch (Throwable $e) {
            Log::warning('Bachs verify errored', ['reference' => $reference, 'message' => $e->getMessage()]);

            return VerifyResult::unavailable();
        }
    }

    private function verifyFromCheckout(string $reference, string $checkoutId): VerifyResult
    {
        $response = $this->client()->get('/v1/checkout-sessions/'.rawurlencode($checkoutId));

        if ($response->status() === 404) {
            // The checkout id came from Bachs, so "not found" here says more about our lookup (wrong
            // environment, rotated key) than about the money. Never terminalize a charge on it.
            Log::warning('Bachs checkout not found while verifying a charge', ['reference' => $reference, 'checkout_id' => $checkoutId]);

            return VerifyResult::unavailable();
        }

        if ($response->serverError() || $response->status() === 0 || $response->failed()) {
            return VerifyResult::unavailable();
        }

        $charge = (array) $response->json('charge', []);

        return new VerifyResult(
            ok: true,
            status: self::resolveStatus((string) ($charge['status'] ?? $response->json('payment_status') ?? '')),
            amount: (float) ($charge['amount'] ?? $response->json('amount') ?? 0),
            currency: strtoupper((string) ($charge['currency'] ?? $response->json('currency') ?? $this->chargeCurrency())),
            reference: (string) ($response->json('reference') ?: $reference),
            gatewayReference: $checkoutId,
        );
    }

    /**
     * Fallback lookup for a charge with no recorded checkout id: page through the newest payments
     * (newest first) and match our reference, which Bachs echoes as the checkout reference.
     */
    private function verifyFromPaymentScan(string $reference): VerifyResult
    {
        $pages = max(1, (int) config('services.bachs.lookup_scan_pages', 2));

        for ($page = 0; $page < $pages; $page++) {
            $response = $this->client()->get('/v1/payments', [
                'limit' => 100,
                'offset' => $page * 100,
            ]);

            if ($response->failed() || $response->serverError()) {
                return VerifyResult::unavailable();
            }

            $items = (array) $response->json('items', []);

            foreach ($items as $item) {
                if (! is_array($item) || (string) ($item['reference'] ?? '') !== $reference) {
                    continue;
                }

                return new VerifyResult(
                    ok: true,
                    status: self::resolveStatus((string) ($item['status'] ?? '')),
                    amount: (float) ($item['amount_paid'] ?? $item['amount'] ?? 0),
                    currency: strtoupper((string) ($item['currency'] ?? $this->chargeCurrency())),
                    reference: (string) $item['reference'],
                    gatewayReference: filled($item['id'] ?? null) ? (string) $item['id'] : null,
                );
            }

            if ($items === [] || ! (bool) $response->json('pagination.has_more', false)) {
                break;
            }
        }

        return VerifyResult::unavailable();
    }

    /**
     * Fail-closed webhook signature check.
     *
     * Bachs signs every delivery with HMAC-SHA256 over "{timestamp}.{raw body}" using the endpoint's
     * signing secret, sent as `X-Bachs-Signature` and (preferred) `X-Bachs-Signature-V2` =
     * `t={timestamp},v1={signature}` — V2 may repeat `v1=` while a secret rotation is in flight, and
     * ANY match is valid. A blank secret, a missing/stale timestamp or a missing signature is refused,
     * so an unsigned POST can never settle a charge.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('services.bachs.webhook_secret');

        if (blank($secret)) {
            return false;
        }

        $timestamp = (string) $request->header('X-Bachs-Timestamp', '');

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $tolerance = max(0, (int) config('services.bachs.webhook_tolerance', 300));

        if (abs(now()->getTimestamp() - (int) $timestamp) > $tolerance) {
            return false; // stale or replayed delivery
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        $v2 = (string) $request->header('X-Bachs-Signature-V2', '');

        if ($v2 !== '') {
            foreach (explode(',', $v2) as $part) {
                $part = trim($part);

                if (str_starts_with($part, 'v1=') && hash_equals($expected, substr($part, 3))) {
                    return true;
                }
            }

            return false;
        }

        $v1 = (string) $request->header('X-Bachs-Signature', '');

        return $v1 !== '' && hash_equals($expected, $v1);
    }

    /**
     * Pull OUR reference out of a charge event.
     *
     * `collection.succeeded`/`checkout.completed` carry `checkout_id`, and the checkout session echoes
     * the reference we started it with — that lookup is what maps the event back onto our charge. The
     * event's own amount/status are deliberately NOT trusted: settlement always re-verifies
     * server-side. Non-charge events (payouts, refunds, disputes, subscriptions) are ignored.
     */
    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $type = (string) $request->input('type', '');

        if (! in_array($type, self::CHARGE_EVENTS, true)) {
            return null;
        }

        $data = (array) $request->input('data', []);
        $checkoutId = (string) ($data['checkout_id'] ?? '');

        $reference = (string) ($data['reference'] ?? $data['metadata']['reference'] ?? '');

        if ($reference === '' && $checkoutId !== '' && $this->configured()) {
            $reference = $this->referenceForCheckout($checkoutId);
        }

        if ($reference === '') {
            return null;
        }

        $status = match ($type) {
            'collection.succeeded', 'checkout.completed' => VerifyResult::STATUS_SUCCESS,
            'collection.failed', 'checkout.expired' => VerifyResult::STATUS_FAILED,
            default => VerifyResult::STATUS_PENDING, // collection.underpaid — let the verify + amount gate decide
        };

        return new WebhookEvent($reference, $status, $checkoutId !== '' ? $checkoutId : null);
    }

    /** The reference a checkout session was started with, or '' when it cannot be established. */
    private function referenceForCheckout(string $checkoutId): string
    {
        try {
            $response = $this->client()->get('/v1/checkout-sessions/'.rawurlencode($checkoutId));

            if ($response->failed()) {
                return '';
            }

            return (string) ($response->json('reference') ?: '');
        } catch (Throwable $e) {
            Log::warning('Bachs webhook could not be mapped to a reference', ['checkout_id' => $checkoutId, 'message' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Bachs' payment statuses collapsed onto the states settlement understands.
     *
     * `accepted` (an under/overpayment treated as final) and `overpaid` are definitive settlements —
     * the mandatory amount reconciliation in the settlement engine decides whether the amount matches
     * what the purchase asked for; `underpaid` too, so a short crypto payment is failed and FLAGGED
     * rather than left pending for a day. A refund means the money did not stay with us, so it must
     * never grant an entitlement. An unknown word stays `pending`: never terminalize on it.
     */
    public static function resolveStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'succeeded', 'success', 'accepted', 'overpaid', 'underpaid', 'paid' => VerifyResult::STATUS_SUCCESS,
            'failed', 'expired', 'cancelled', 'canceled', 'reversed',
            'refunded', 'partially_refunded', 'auto_refunded' => VerifyResult::STATUS_FAILED,
            default => VerifyResult::STATUS_PENDING,
        };
    }

    /** The amount as Bachs wants it: a decimal STRING with two places in the major unit. */
    public static function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    /**
     * Optional corridor restriction (config `bachs.payment_method_types`, comma separated). Empty means
     * every method the account is enabled for is offered — the safe default.
     *
     * @return list<string>
     */
    private static function paymentMethods(): array
    {
        $configured = config('services.bachs.payment_method_types', '');

        $methods = is_array($configured) ? $configured : explode(',', (string) $configured);

        return array_values(array_filter(array_map(
            fn ($method): string => strtoupper(trim((string) $method)),
            $methods,
        )));
    }

    private static function messageFrom(\Illuminate\Http\Client\Response $response, string $fallback): string
    {
        foreach ([$response->json('detail'), $response->json('message'), $response->json('error_code')] as $candidate) {
            if (is_string($candidate) && filled($candidate)) {
                return $candidate;
            }
        }

        return $fallback;
    }
}
