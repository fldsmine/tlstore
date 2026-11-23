<?php

namespace App\Contracts;

use App\Services\Payments\DTO\ChargeRequest;
use App\Services\Payments\DTO\InitiateResult;
use App\Services\Payments\DTO\VerifyResult;
use App\Services\Payments\DTO\WebhookEvent;
use Illuminate\Http\Request;

/**
 * A payment provider that can collect a one-off charge (hosted checkout) and be settled
 * server-side. The originating provider is pinned on the transaction row, so verify/requery/
 * webhook always route back to the driver that created the charge — even after an admin
 * switches the configured provider.
 */
interface PaymentGateway
{
    /** Machine name — one of App\Enums\PaymentProvider values (korapay|paystack|flutterwave). */
    public function name(): string;

    /** True only when this provider's secret key is present. */
    public function configured(): bool;

    /** The currency this provider charges in (e.g. NGN). */
    public function chargeCurrency(): string;

    /** Start a hosted checkout for the charge. */
    public function initiate(ChargeRequest $request): InitiateResult;

    /** Authoritatively confirm a charge server-side by our reference (used at settle/requery). */
    public function verify(string $reference): VerifyResult;

    /** Fail-closed signature check for an incoming webhook (false when the secret is blank). */
    public function verifyWebhookSignature(Request $request): bool;

    /** Extract our reference from a webhook payload; null when it is not a charge event we handle. */
    public function parseWebhook(Request $request): ?WebhookEvent;
}
