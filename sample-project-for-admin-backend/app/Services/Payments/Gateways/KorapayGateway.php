<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\PaymentGateway;
use App\Services\Payments\DTO\ChargeRequest;
use App\Services\Payments\DTO\InitiateResult;
use App\Services\Payments\DTO\VerifyResult;
use App\Services\Payments\DTO\WebhookEvent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class KorapayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'korapay';
    }

    public function configured(): bool
    {
        return filled(config('services.korapay.secret_key'));
    }

    public function chargeCurrency(): string
    {
        return (string) config('services.korapay.currency', 'NGN');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.korapay.base_url'))
            ->withToken((string) config('services.korapay.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    public function initiate(ChargeRequest $request): InitiateResult
    {
        try {
            $response = $this->client()->post('/merchant/api/v1/charges/initialize', [
                'reference' => $request->reference,
                'amount' => round($request->amount, 2),
                'currency' => $request->currency,
                'redirect_url' => $request->callbackUrl,
                'notification_url' => $request->notificationUrl,
                'narration' => $request->narration,
                'customer' => ['name' => $request->customerName, 'email' => $request->email],
                'metadata' => $request->metadata,
            ]);

            $url = (string) $response->json('data.checkout_url', '');

            if ($response->failed() || ! $response->json('status') || $url === '') {
                return InitiateResult::failure((string) $response->json('message', 'Payment initialization failed'));
            }

            return InitiateResult::success($url, (string) $response->json('data.reference', $request->reference));
        } catch (Throwable $e) {
            return InitiateResult::failure('Unable to initialize payment');
        }
    }

    public function verify(string $reference): VerifyResult
    {
        try {
            $response = $this->client()->get("/merchant/api/v1/charges/{$reference}");

            if ($response->failed() || ! $response->json('status')) {
                return VerifyResult::unavailable();
            }

            $data = (array) $response->json('data', []);

            return new VerifyResult(
                ok: true,
                status: self::resolveStatus((string) ($data['status'] ?? 'pending')),
                amount: (float) ($data['amount'] ?? 0),
                currency: (string) ($data['currency'] ?? $this->chargeCurrency()),
                reference: self::ourReference($data, $reference),
                gatewayReference: self::theirReference($data, $reference),
            );
        } catch (Throwable $e) {
            return VerifyResult::unavailable();
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('services.korapay.secret_key');
        $signature = (string) $request->header('x-korapay-signature', '');

        if (blank($secret) || $signature === '') {
            return false;
        }

        $payload = json_encode($request->input('data'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expected = hash_hmac('sha256', (string) $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $data = (array) $request->input('data', []);
        $reference = (string) ($data['reference'] ?? '');

        if ($reference === '') {
            return null;
        }

        return new WebhookEvent($reference, self::resolveStatus((string) ($data['status'] ?? 'pending')));
    }

    /**
     * OUR reference as Korapay reports it: `payment_reference` echoes what we sent the charge with,
     * while `reference` may carry Korapay's own transaction id (KPY-PAY-…). Reconciliation compares
     * this against the charge row, so prefer the echoed payment reference.
     *
     * @param  array<string, mixed>  $data
     */
    private static function ourReference(array $data, string $fallback): string
    {
        foreach (['payment_reference', 'reference'] as $key) {
            if (filled($data[$key] ?? null)) {
                return (string) $data[$key];
            }
        }

        return $fallback;
    }

    /**
     * The provider's OWN transaction id when it is distinguishable from our reference — the second
     * half of the single-use guarantee (one real payment must never settle two charges).
     *
     * @param  array<string, mixed>  $data
     */
    private static function theirReference(array $data, string $ours): ?string
    {
        foreach (['transaction_reference', 'reference'] as $key) {
            $value = $data[$key] ?? null;

            if (filled($value) && (string) $value !== $ours) {
                return (string) $value;
            }
        }

        return null;
    }

    private static function resolveStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'processed' => VerifyResult::STATUS_SUCCESS,
            'failed', 'expired', 'cancelled', 'reversed' => VerifyResult::STATUS_FAILED,
            default => VerifyResult::STATUS_PENDING,
        };
    }
}
