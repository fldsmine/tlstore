<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Enums\TransactionType;
use App\Mail\ProductDelivery;
use App\Mail\ProductOrderConfirmation;
use App\Mail\ProductOrderNotice;
use App\Models\PaymentResolutionLog;
use App\Models\ProductOrder;
use App\Services\Payments\DTO\ChargeRequest;
use App\Services\Payments\DTO\VerifyResult;
use App\Support\ProductCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * Guest eSIM orders from the product pages: start a checkout, settle it against the gateway, and
 * hand the paid order to a human for delivery.
 *
 * The settlement rules are the ones every other charge in this app already follows, because the
 * money is just as real here even though the buyer never signed in:
 *
 *   - the charge is re-verified SERVER-SIDE (the redirect back, the webhook and the requery sweep all
 *     land here) — nothing the browser says is trusted;
 *   - the amount, the currency and the reference are reconciled against what the order asked for;
 *   - the reference is claimed in the single-use ledger BEFORE the order is marked paid, so a
 *     replayed callback/webhook can never fulfil the same payment twice;
 *   - an answer that is not definitive (transport error, 5xx) leaves the order PENDING rather than
 *     failing it — the buyer may well have paid.
 *
 * Settlement never delivers anything by itself: it marks the order paid, tells the buyer, and puts the
 * order in the fulfilment queue for staff. That matches how these sales are actually fulfilled.
 */
class ProductOrderService
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private PaymentReferenceLedger $ledger,
    ) {}

    /** The provider this storefront checks out with (Paystack by default). */
    public function provider(): string
    {
        return (string) config('product.provider', 'paystack');
    }

    /**
     * Start a checkout for a guest: create the order, ask the gateway for a payment link, and hand
     * that link back for the browser to follow.
     *
     * @param  array{full_name: string, phone: string, email: string}  $buyer
     * @return array{ok: bool, error?: string, not_found?: bool, order?: ProductOrder, checkout_url?: string}
     */
    public function start(string $slug, array $buyer, ?string $provider = null): array
    {
        $product = ProductCatalog::find($slug);

        if ($product === null || ! $product['orderable']) {
            return ['ok' => false, 'not_found' => $product === null, 'error' => 'That product is not available right now.'];
        }

        $amountNgn = round((float) $product['price_ngn'], 2);

        if ($amountNgn < 1) {
            return ['ok' => false, 'error' => 'This product cannot be purchased right now — please try again shortly.'];
        }

        $provider = $provider !== null && $provider !== '' ? $provider : $this->provider();

        try {
            $gateway = $this->gateways->driver($provider);
        } catch (InvalidArgumentException $e) {
            Log::critical('Product page is configured with an unknown payment provider', ['provider' => $provider]);

            return ['ok' => false, 'error' => 'Card payments are unavailable right now — please try again shortly.'];
        }

        if (! $gateway->configured()) {
            return ['ok' => false, 'error' => 'Card payments are unavailable right now — please try again shortly.'];
        }

        $reference = ProductOrder::generateReference();

        // The price is FROZEN on the order here: if the package price or the FX rate changes while
        // the buyer is on the gateway's page, this order still charges exactly what it displayed.
        $order = ProductOrder::create([
            'reference' => $reference,
            'source' => (string) $product['source'],
            'package_id' => $product['package_id'],
            'product_id' => $product['product_id'],
            'product_slug' => $product['slug'],
            'product_name' => $product['name'],
            'full_name' => $buyer['full_name'],
            'phone' => $buyer['phone'],
            'email' => $buyer['email'],
            'amount_usd' => $product['price_usd'],
            'amount_ngn' => $amountNgn,
            'currency' => strtoupper((string) ($product['currency'] ?? config('product.currency', 'NGN'))),
            'provider' => $provider,
            'status' => ProductOrder::STATUS_PENDING,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'meta' => [
                'channel' => 'product_page',
                'source' => (string) $product['source'],
                'region' => (string) config('product.region', 'nigeria'),
                'product_page_host' => request()->getHost(),
            ],
        ]);

        $result = $gateway->initiate(new ChargeRequest(
            reference: $reference,
            amount: $amountNgn,
            currency: (string) $order->currency,
            email: (string) $order->email,
            customerName: (string) $order->full_name,
            narration: $product['name'],
            callbackUrl: route('product.callback', ['reference' => $reference]),
            notificationUrl: route('webhooks.payments', ['provider' => $provider]),
            metadata: [
                'reference' => $reference,
                'product' => $product['slug'],
                'product_order_id' => $order->id,
                'buyer_phone' => (string) $order->phone,
                'channel' => 'product_page',
            ],
        ));

        if (! $result->ok || blank($result->checkoutUrl)) {
            $order->update([
                'status' => ProductOrder::STATUS_FAILED,
                'failure_reason' => $result->message ?? 'The payment link could not be created.',
            ]);

            Log::warning('Product order could not create a payment link', [
                'reference' => $reference,
                'provider' => $provider,
                'message' => $result->message,
            ]);

            return ['ok' => false, 'error' => 'We could not open the payment page. Please try again.'];
        }

        $order->update([
            'checkout_url' => (string) $result->checkoutUrl,
            'gateway_reference' => $result->gatewayReference ?: $reference,
        ]);

        return ['ok' => true, 'order' => $order, 'checkout_url' => (string) $result->checkoutUrl];
    }

    /**
     * Settle a guest order by OUR reference.
     *
     * @return string 'completed' (paid) | 'pending' | 'failed' | 'unavailable' (no definitive answer)
     */
    public function settle(string $reference): string
    {
        $order = ProductOrder::query()->where('reference', $reference)->first();

        if ($order === null) {
            return 'failed';
        }

        if ($order->status === ProductOrder::STATUS_PAID) {
            return 'completed';
        }

        if ($order->status !== ProductOrder::STATUS_PENDING) {
            return 'failed';
        }

        $result = $this->verifyOrder($order);

        if (! $result->ok) {
            return 'unavailable';
        }

        if ($result->status === VerifyResult::STATUS_PENDING) {
            return 'pending';
        }

        return $this->settleVerified($order, $result, allowFailed: false);
    }

    /**
     * Backoffice provider recheck. A failed order can be recovered only when its original pinned
     * provider now authoritatively confirms a matching payment.
     *
     * @param  array<string, mixed>  $resolution
     */
    public function reverifyForBackoffice(string $reference, array $resolution): string
    {
        $order = ProductOrder::query()->where('reference', $reference)->first();

        if ($order === null) {
            return 'failed';
        }

        if ($order->status === ProductOrder::STATUS_PAID) {
            return 'completed';
        }

        if (! in_array($order->status, [ProductOrder::STATUS_PENDING, ProductOrder::STATUS_FAILED], true)) {
            return 'failed';
        }

        if ($this->ledger->isSettled($reference)) {
            $this->writeResolution($order, $resolution, $order->status, $order->status, ['outcome' => 'already_settled']);

            return $order->status;
        }

        $before = $order->status;
        $result = $this->verifyOrder($order);
        $context = $this->withVerificationResult($resolution, $result);

        if (! $result->ok) {
            $this->writeResolution($order, $context, $before, $order->status, ['outcome' => 'unavailable']);

            return 'unavailable';
        }

        if ($result->status === VerifyResult::STATUS_PENDING) {
            $this->writeResolution($order, $context, $before, $order->status, ['outcome' => 'pending']);

            return 'pending';
        }

        if ($result->failed() && $before === ProductOrder::STATUS_FAILED) {
            $this->writeResolution($order, $context, $before, $order->status, ['outcome' => 'gateway_reported_failed']);

            return 'failed';
        }

        return $this->settleVerified($order, $result, allowFailed: true, resolution: $context);
    }

    /**
     * Complete a guest order after documented backoffice confirmation, using the same reconciliation,
     * ledger, paid-state and once-only notification path as automatic provider settlement.
     *
     * @param  array<string, mixed>  $resolution
     */
    public function settleManually(string $reference, VerifyResult $result, array $resolution): string
    {
        $order = ProductOrder::query()->where('reference', $reference)->first();

        if ($order === null) {
            return 'failed';
        }

        if ($order->status === ProductOrder::STATUS_PAID) {
            return 'completed';
        }

        if (! in_array($order->status, [ProductOrder::STATUS_PENDING, ProductOrder::STATUS_FAILED], true)) {
            return 'failed';
        }

        if ($this->ledger->isSettled($reference)) {
            $this->writeResolution($order, $resolution, $order->status, $order->status, ['outcome' => 'already_settled']);

            return $order->status;
        }

        if (! $result->succeeded()) {
            $this->writeResolution($order, $resolution, $order->status, $order->status, ['outcome' => 'invalid_manual_verification']);

            return $order->status;
        }

        return $this->settleVerified($order, $result, allowFailed: true, resolution: $resolution);
    }

    private function verifyOrder(ProductOrder $order): VerifyResult
    {
        $provider = (string) $order->provider;

        if (! in_array($provider, PaymentProvider::values(), true)) {
            Log::critical('Product order has no resolvable provider', ['reference' => $order->reference, 'provider' => $provider]);

            return VerifyResult::unavailable();
        }

        try {
            return $this->gateways->driver($provider)->verify((string) $order->reference);
        } catch (Throwable $e) {
            Log::warning('Product order verification could not be completed', [
                'reference' => $order->reference,
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return VerifyResult::unavailable();
        }
    }

    private function settleVerified(
        ProductOrder $order,
        VerifyResult $result,
        bool $allowFailed,
        ?array $resolution = null,
    ): string {
        $provider = (string) $order->provider;

        $outcome = DB::transaction(function () use ($order, $result, $provider, $allowFailed, $resolution): array {
            $locked = ProductOrder::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null) {
                return ['status' => 'failed', 'notify' => false];
            }

            if ($locked->status === ProductOrder::STATUS_PAID) {
                $this->writeResolution($locked, $resolution, ProductOrder::STATUS_PAID, ProductOrder::STATUS_PAID, ['outcome' => 'already_completed']);

                return ['status' => 'completed', 'notify' => false]; // race / replay guard
            }

            if ($locked->status !== ProductOrder::STATUS_PENDING
                && ! ($allowFailed && $locked->status === ProductOrder::STATUS_FAILED)) {
                return ['status' => 'failed', 'notify' => false];
            }

            $fromStatus = (string) $locked->status;

            if ($result->failed()) {
                if ($locked->status === ProductOrder::STATUS_PENDING) {
                    $locked->update([
                        'status' => ProductOrder::STATUS_FAILED,
                        'failure_reason' => 'The gateway reported the payment as '.$result->status.'.',
                        'gateway_response' => ['status' => $result->status, 'reference' => $result->reference],
                    ]);
                }

                $this->writeResolution($locked, $resolution, $fromStatus, (string) $locked->status, [
                    'outcome' => 'gateway_reported_failed',
                    'verified_reference' => $result->reference,
                    'verified_currency' => $result->currency,
                    'verified_amount' => round($result->amount, 2),
                ]);

                return ['status' => 'failed', 'notify' => false];
            }

            if (! $result->succeeded()) {
                return ['status' => 'pending', 'notify' => false];
            }

            // Reconcile the actual charge against the order before marking it paid.
            $expectedNgn = round((float) $locked->amount_ngn, 2);
            $expectedCurrency = strtoupper((string) $locked->currency);

            if ($result->reference !== $locked->reference
                || strtoupper($result->currency) !== $expectedCurrency
                || $result->amount < $expectedNgn - 0.01) {
                $mismatch = [
                    'reference' => $result->reference,
                    'currency' => $result->currency,
                    'amount' => round($result->amount, 2),
                    'flagged_at' => now()->toIso8601String(),
                ];

                $locked->update([
                    'status' => ProductOrder::STATUS_FAILED,
                    'failure_reason' => 'The payment did not match this order.',
                    'gateway_response' => ['status' => $result->status, 'reference' => $result->reference, 'amount' => $result->amount, 'currency' => $result->currency],
                    'meta' => array_merge((array) $locked->meta, ['reconcile_error' => $mismatch]),
                ]);

                Log::critical('Product order reconciliation mismatch', ['reference' => $locked->reference, 'expected' => $expectedNgn, 'got' => $result->amount]);
                $this->writeResolution($locked, $resolution, $fromStatus, ProductOrder::STATUS_FAILED, [
                    'outcome' => 'reconciliation_mismatch',
                    'reconcile_error' => $mismatch,
                ]);

                return ['status' => 'failed', 'notify' => false];
            }

            $ledgerMeta = [
                'product_order_id' => $locked->id,
                'product' => $locked->product_slug,
                'product_source' => (string) $locked->source,
                'channel' => 'product_page',
                'verified_amount' => round((float) $result->amount, 2),
                'gateway_status' => $result->status,
            ];

            if ($resolution !== null) {
                $ledgerMeta['backoffice_resolution'] = $this->resolutionSnapshot($resolution);
            }

            $claim = $this->ledger->claimForOrder(
                reference: (string) $locked->reference,
                provider: $provider,
                gatewayReference: $result->gatewayReference,
                type: TransactionType::PACKAGE_PURCHASE->value,
                amountNgn: $expectedNgn,
                currency: $expectedCurrency,
                meta: $ledgerMeta,
            );

            if ($claim === null) {
                $locked->update([
                    'status' => ProductOrder::STATUS_FAILED,
                    'failure_reason' => 'This payment reference has already been settled.',
                    'meta' => array_merge((array) $locked->meta, ['manual_review' => [
                        'reason' => 'duplicate_reference',
                        'error' => 'This payment reference has already been settled by another order.',
                        'flagged_at' => now()->toIso8601String(),
                    ]]),
                ]);

                Log::critical('Duplicate payment reference — guest order failed without delivering', [
                    'reference' => $locked->reference,
                    'provider' => $provider,
                    'gateway_reference' => $result->gatewayReference,
                ]);

                $this->writeResolution($locked, $resolution, $fromStatus, ProductOrder::STATUS_FAILED, ['outcome' => 'duplicate_reference']);

                return ['status' => 'failed', 'notify' => false];
            }

            // Claim notification slots in the same transaction as the payment ledger and paid state.
            $notify = $locked->confirmation_sent_at === null;
            $meta = (array) $locked->meta;
            if ($resolution !== null) {
                $meta['payment_resolution'] = $this->resolutionSnapshot($resolution);
            }

            $locked->update([
                'status' => ProductOrder::STATUS_PAID,
                'paid_at' => now(),
                'gateway_reference' => $result->gatewayReference ?: $locked->gateway_reference,
                'gateway_response' => ['status' => $result->status, 'reference' => $result->reference, 'amount' => $result->amount, 'currency' => $result->currency],
                'confirmation_sent_at' => $notify ? now() : $locked->confirmation_sent_at,
                'ops_notified_at' => $locked->ops_notified_at ?? now(),
                'failure_reason' => null,
                'meta' => $meta,
            ]);

            $this->writeResolution($locked, $resolution, $fromStatus, ProductOrder::STATUS_PAID, ['outcome' => 'completed']);

            return ['status' => 'completed', 'notify' => $notify];
        });

        if ($outcome['notify']) {
            $this->announce($order->refresh());
        }

        return $outcome['status'];
    }

    /** @return array<string, mixed> */
    private function withVerificationResult(array $resolution, VerifyResult $result): array
    {
        return array_merge($resolution, [
            'amount' => $result->ok ? round($result->amount, 2) : null,
            'currency' => $result->ok ? strtoupper($result->currency) : null,
            'gateway_reference' => $result->gatewayReference,
            'details' => [
                'provider_status' => $result->status,
                'provider_reference' => $result->reference,
                'provider_answered' => $result->ok,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function resolutionSnapshot(array $resolution): array
    {
        return [
            'action' => $resolution['action'] ?? null,
            'admin_id' => $resolution['admin_id'] ?? null,
            'verification_source' => $resolution['verification_source'] ?? null,
            'evidence_reference' => $resolution['evidence_reference'] ?? null,
            'gateway_reference' => $resolution['gateway_reference'] ?? null,
            'amount' => $resolution['amount'] ?? null,
            'currency' => $resolution['currency'] ?? null,
            'note' => $resolution['note'] ?? null,
            'resolved_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed>|null $resolution @param array<string, mixed> $details */
    private function writeResolution(ProductOrder $order, ?array $resolution, string $fromStatus, string $toStatus, array $details = []): void
    {
        if ($resolution === null || blank($resolution['action'] ?? null)) {
            return;
        }

        PaymentResolutionLog::query()->create([
            'admin_id' => $resolution['admin_id'] ?? null,
            'product_order_id' => $order->id,
            'reference' => (string) $order->reference,
            'provider' => $order->provider,
            'action' => $resolution['action'],
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'verification_source' => $resolution['verification_source'] ?? null,
            'evidence_reference' => $resolution['evidence_reference'] ?? null,
            'gateway_reference' => $resolution['gateway_reference'] ?? null,
            'amount' => $resolution['amount'] ?? null,
            'currency' => $resolution['currency'] ?? null,
            'note' => $resolution['note'] ?? null,
            'details' => array_merge((array) ($resolution['details'] ?? []), $details),
            'resolved_at' => now(),
        ]);
    }

    /**
     * Tell the buyer we have their money, and nudge whoever fulfils these orders.
     *
     * A mail problem must never undo a settled payment, so each send is guarded and logged.
     */
    public function announce(ProductOrder $order): void
    {
        try {
            Mail::to($order->email)->send(new ProductOrderConfirmation($order));
        } catch (Throwable $e) {
            Log::warning('Product order confirmation could not be sent', ['reference' => $order->reference, 'message' => $e->getMessage()]);
        }

        $opsEmail = (string) (config('product.ops_email') ?: config('settings.contact.email'));

        if (blank($opsEmail)) {
            return;
        }

        try {
            Mail::to($opsEmail)->send(new ProductOrderNotice($order));
        } catch (Throwable $e) {
            Log::warning('Product order notice could not be sent', ['reference' => $order->reference, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Send the product to the buyer by hand (backoffice fulfilment) and record that it happened.
     *
     * @return array{ok: bool, error?: string}
     */
    public function deliver(ProductOrder $order, string $details, ?string $note = null): array
    {
        if (! $order->isPaid()) {
            return ['ok' => false, 'error' => 'This order has not been paid — there is nothing to send yet.'];
        }

        $details = trim($details);

        if ($details === '') {
            return ['ok' => false, 'error' => 'Add the product details to send to the buyer.'];
        }

        try {
            Mail::to($order->email)->send(new ProductDelivery($order, $details, $note));
        } catch (Throwable $e) {
            Log::error('Product delivery email failed', ['reference' => $order->reference, 'message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'The email could not be sent — please try again.'];
        }

        // Only the fact + the staff note are stored; the specifics that were emailed are not kept on
        // the order, so a database leak cannot hand out live activation details.
        $order->update([
            'fulfilled_at' => now(),
            'fulfilment_note' => $note ?: $order->fulfilment_note,
        ]);

        return ['ok' => true];
    }

}
