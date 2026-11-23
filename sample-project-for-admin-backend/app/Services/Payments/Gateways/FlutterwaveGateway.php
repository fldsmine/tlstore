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

class FlutterwaveGateway implements BankVerifier, PaymentGateway
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function configured(): bool
    {
        return filled(config('services.flutterwave.secret_key'));
    }

    public function chargeCurrency(): string
    {
        return (string) config('services.flutterwave.currency', 'NGN');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.flutterwave.base_url', 'https://api.flutterwave.com'))
            ->withToken((string) config('services.flutterwave.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    public function initiate(ChargeRequest $request): InitiateResult
    {
        try {
            $response = $this->client()->post('/v3/payments', [
                'tx_ref' => $request->reference,
                'amount' => round($request->amount, 2),
                'currency' => $request->currency,
                'redirect_url' => $request->callbackUrl,
                'customer' => ['email' => $request->email, 'name' => $request->customerName],
                'customizations' => ['title' => $request->narration],
                'meta' => $request->metadata,
            ]);

            $url = (string) $response->json('data.link', '');

            if ($response->failed() || $response->json('status') !== 'success' || $url === '') {
                return InitiateResult::failure((string) $response->json('message', 'Payment initialization failed'));
            }

            return InitiateResult::success($url);
        } catch (Throwable $e) {
            return InitiateResult::failure('Unable to initialize payment');
        }
    }

    public function verify(string $reference): VerifyResult
    {
        try {
            // verify_by_reference keys on OUR tx_ref, so requery works with no redirect params.
            $response = $this->client()->get('/v3/transactions/verify_by_reference', ['tx_ref' => $reference]);

            if ($response->failed() || $response->json('status') !== 'success') {
                return VerifyResult::unavailable();
            }

            $charged = $response->json('data.charged_amount');

            return new VerifyResult(
                ok: true,
                status: self::resolveStatus((string) $response->json('data.status', 'pending')),
                amount: (float) ($charged ?? $response->json('data.amount', 0)),
                currency: (string) $response->json('data.currency', $this->chargeCurrency()),
                reference: (string) $response->json('data.tx_ref', $reference),
                gatewayReference: $response->json('data.id') !== null ? (string) $response->json('data.id') : null,
            );
        } catch (Throwable $e) {
            return VerifyResult::unavailable();
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $hash = (string) config('services.flutterwave.secret_hash');
        $signature = (string) $request->header('verif-hash', '');

        if (blank($hash) || $signature === '') {
            return false;
        }

        return hash_equals($hash, $signature);
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $reference = (string) $request->input('data.tx_ref', '');

        if ($reference === '') {
            return null;
        }

        $id = $request->input('data.id');

        return new WebhookEvent(
            $reference,
            self::resolveStatus((string) $request->input('data.status', 'pending')),
            $id !== null ? (string) $id : null,
        );
    }

    public function resolveBankAccount(string $accountNumber, string $bankCode): array
    {
        try {
            $response = $this->client()->post('/v3/accounts/resolve', [
                'account_bank' => $bankCode,
                'account_number' => $accountNumber,
            ]);

            if ($response->failed() || $response->json('status') !== 'success') {
                return ['status' => 'error', 'message' => $response->json('message') ?? 'Could not verify this account. Check the details and try again.'];
            }

            return [
                'status' => 'success',
                'data' => [
                    'provider' => 'flutterwave',
                    'bank_code' => $bankCode,
                    'account_number' => $accountNumber,
                    'account_name' => (string) $response->json('data.account_name'),
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Flutterwave account resolve failed', ['message' => $e->getMessage()]);

            return ['status' => 'error', 'message' => 'Bank verification is temporarily unavailable. Please try again.'];
        }
    }

    private static function resolveStatus(string $status): string
    {
        return match (strtolower($status)) {
            'successful' => VerifyResult::STATUS_SUCCESS,
            'failed', 'cancelled' => VerifyResult::STATUS_FAILED,
            default => VerifyResult::STATUS_PENDING,
        };
    }
}
