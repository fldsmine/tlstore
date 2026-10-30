<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\BankVerifier;
use App\Contracts\PaymentGateway;
use App\Services\Payments\DTO\ChargeRequest;
use App\Services\Payments\DTO\InitiateResult;
use App\Services\Payments\DTO\VerifyResult;
use App\Services\Payments\DTO\WebhookEvent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackGateway implements BankVerifier, PaymentGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    public function configured(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    public function chargeCurrency(): string
    {
        return (string) config('services.paystack.currency', 'NGN');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paystack.base_url', 'https://api.paystack.co'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    public function initiate(ChargeRequest $request): InitiateResult
    {
        try {
            // Paystack has no per-charge notification URL (it is dashboard-set); amount is in kobo.
            $response = $this->client()->post('/transaction/initialize', [
                'email' => $request->email,
                'amount' => (int) round($request->amount * 100),
                'currency' => $request->currency,
                'reference' => $request->reference,
                'callback_url' => $request->callbackUrl,
                'metadata' => array_merge($request->metadata, ['narration' => $request->narration]),
            ]);

            $url = (string) $response->json('data.authorization_url', '');

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
            $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));

            if ($response->failed() || ! $response->json('status')) {
                return VerifyResult::unavailable();
            }

            return new VerifyResult(
                ok: true,
                status: self::resolveStatus((string) $response->json('data.status', 'pending')),
                amount: (float) $response->json('data.amount', 0) / 100,
                currency: (string) $response->json('data.currency', $this->chargeCurrency()),
                reference: (string) $response->json('data.reference', $reference),
            );
        } catch (Throwable $e) {
            return VerifyResult::unavailable();
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('services.paystack.secret_key');
        $signature = (string) $request->header('x-paystack-signature', '');

        if (blank($secret) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        if ($request->input('event') !== 'charge.success') {
            return null;
        }

        $reference = (string) $request->input('data.reference', '');

        if ($reference === '') {
            return null;
        }

        return new WebhookEvent($reference, self::resolveStatus((string) $request->input('data.status', 'pending')));
    }

    public function resolveBankAccount(string $accountNumber, string $bankCode): array
    {
        try {
            $response = $this->client()->get('/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

            if ($response->failed() || ! $response->json('status')) {
                return ['status' => 'error', 'message' => $response->json('message') ?? 'Could not verify this account. Check the details and try again.'];
            }

            return [
                'status' => 'success',
                'data' => [
                    'provider' => 'paystack',
                    'bank_code' => $bankCode,
                    'account_number' => (string) $response->json('data.account_number'),
                    'account_name' => (string) $response->json('data.account_name'),
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Paystack account resolve failed', ['message' => $e->getMessage()]);

            return ['status' => 'error', 'message' => 'Bank verification is temporarily unavailable. Please try again.'];
        }
    }

    private static function resolveStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success' => VerifyResult::STATUS_SUCCESS,
            'failed', 'abandoned', 'reversed' => VerifyResult::STATUS_FAILED,
            default => VerifyResult::STATUS_PENDING,
        };
    }
}
