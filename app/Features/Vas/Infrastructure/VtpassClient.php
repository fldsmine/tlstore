<?php

namespace App\Features\Vas\Infrastructure;

use App\Application\Money\CurrencyMinorUnits;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Thin HTTP boundary for VTpass. Do not leak credentials or raw responses to callers. */
final class VtpassClient
{
    public function configured(): bool
    {
        return filled(config('services.vtpass.api_key'))
            && filled(config('services.vtpass.secret_key'))
            && filled(config('services.vtpass.public_key'));
    }

    /** @return array{ok:bool,variations:list<array{code:string,name:string,amount_minor:int,fixed_price:bool}>,message:string} */
    public function variations(string $serviceId): array
    {
        try {
            $response = $this->client(publicKey: true)
                ->get('api/service-variations', ['serviceID' => $serviceId]);
            $json = (array) $response->json();
            $rows = data_get($json, 'content.variations', []);

            if (! $response->successful() || ! is_array($rows)) {
                return ['ok' => false, 'variations' => [], 'message' => 'VTpass could not provide plans for this service.'];
            }

            $variations = collect($rows)
                ->filter(fn ($row): bool => is_array($row))
                ->map(fn (array $row): array => [
                    'code' => trim((string) ($row['variation_code'] ?? '')),
                    'name' => trim((string) ($row['name'] ?? '')),
                    'amount_minor' => is_numeric($row['variation_amount'] ?? null)
                        ? CurrencyMinorUnits::toMinor((string) $row['variation_amount'], 'NGN')
                        : null,
                    'fixed_price' => filter_var($row['fixedPrice'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ])
                ->filter(fn (array $row): bool => $row['code'] !== '' && $row['name'] !== '' && $row['amount_minor'] !== null)
                ->values()
                ->all();

            if ($variations === []) {
                return ['ok' => false, 'variations' => [], 'message' => 'No plans are currently available for this service.'];
            }

            return ['ok' => true, 'variations' => $variations, 'message' => ''];
        } catch (Throwable $exception) {
            Log::warning('VTpass catalog request failed', [
                'service_id' => $serviceId,
                'exception' => $exception::class,
            ]);

            return ['ok' => false, 'variations' => [], 'message' => 'VTpass is temporarily unavailable. Please try again.'];
        }
    }

    /** @param array<string,mixed> $payload @return array{reached:bool,status:string,external_id:?string,token:?string,code:?string,message:string} */
    public function purchase(array $payload): array
    {
        try {
            $response = $this->client()->asForm()->post('api/pay', $payload);
            return $this->normalizeOutcome($response);
        } catch (Throwable $exception) {
            Log::warning('VTpass purchase request failed', [
                'request_id' => $payload['request_id'] ?? null,
                'service_id' => $payload['serviceID'] ?? null,
                'exception' => $exception::class,
            ]);

            return [
                'reached' => false,
                'status' => 'pending',
                'external_id' => null,
                'token' => null,
                'code' => null,
                'message' => 'VTpass is still confirming this request.',
            ];
        }
    }

    /** @return array{reached:bool,status:string,external_id:?string,token:?string,code:?string,message:string} */
    public function requery(string $requestId): array
    {
        try {
            $response = $this->client()->asForm()->post('api/requery', ['request_id' => $requestId]);

            return $this->normalizeOutcome($response);
        } catch (Throwable $exception) {
            Log::warning('VTpass requery failed', [
                'request_id' => $requestId,
                'exception' => $exception::class,
            ]);

            return [
                'reached' => false,
                'status' => 'pending',
                'external_id' => null,
                'token' => null,
                'code' => null,
                'message' => 'VTpass is temporarily unavailable. The purchase is still pending.',
            ];
        }
    }

    /** @return array{ok:bool,customer_name:?string,message:string} */
    public function verify(string $serviceId, string $customerId, ?string $type = null): array
    {
        $payload = ['serviceID' => $serviceId, 'billersCode' => $customerId];
        if ($type !== null && $type !== '') {
            $payload['type'] = $type;
        }

        try {
            $response = $this->client()->asForm()->post('api/merchant-verify', $payload);
            $json = (array) $response->json();
            $name = trim((string) (data_get($json, 'content.Customer_Name')
                ?? data_get($json, 'content.customerName')
                ?? data_get($json, 'content.customer_name')
                ?? ''));
            $ok = $response->successful() && (string) ($json['code'] ?? '') === '000' && $name !== '';

            return [
                'ok' => $ok,
                'customer_name' => $ok ? $name : null,
                'message' => $ok ? '' : 'We could not verify those details. Check them and try again.',
            ];
        } catch (Throwable $exception) {
            Log::warning('VTpass recipient verification failed', [
                'service_id' => $serviceId,
                'exception' => $exception::class,
            ]);

            return ['ok' => false, 'customer_name' => null, 'message' => 'Recipient verification is temporarily unavailable. Please try again.'];
        }
    }

    private function client(bool $publicKey = false): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.vtpass.base_url', 'https://sandbox.vtpass.com'), '/'))
            ->acceptJson()
            ->connectTimeout(8)
            ->timeout(max(5, (int) config('services.vtpass.timeout', 20)))
            ->withHeaders([
                'api-key' => (string) config('services.vtpass.api_key'),
                $publicKey ? 'public-key' : 'secret-key' => (string) config($publicKey
                    ? 'services.vtpass.public_key'
                    : 'services.vtpass.secret_key'),
            ]);
    }

    /** @return array{reached:bool,status:string,external_id:?string,token:?string,code:?string,message:string} */
    private function normalizeOutcome(Response $response): array
    {
        $json = (array) $response->json();
        $code = trim((string) ($json['code'] ?? ''));
        $transaction = (array) data_get($json, 'content.transactions', []);
        $providerStatus = strtolower((string) ($transaction['status'] ?? ''));
        $message = trim((string) ($json['response_description'] ?? ''));

        // An HTTP/transport failure or a response without a VTpass transaction code is uncertain;
        // it must remain pending because VTpass may have accepted the original request.
        if (! $response->successful() || $code === '' || $code === 'error') {
            return [
                'reached' => false,
                'status' => 'pending',
                'external_id' => null,
                'token' => null,
                'code' => $code !== '' ? $code : null,
                'message' => 'VTpass is still confirming this request.',
            ];
        }

        $status = match (true) {
            $code === '040', $providerStatus === 'reversed' => 'reversed',
            $code === '016', in_array($providerStatus, ['failed', 'rejected', 'unsuccessful'], true) => 'failed',
            $code === '000' && $providerStatus === 'delivered' => 'delivered',
            default => 'pending',
        };
        $token = $transaction['purchased_code'] ?? $transaction['token'] ?? data_get($json, 'content.token');
        $externalId = $transaction['transactionId'] ?? $transaction['transaction_id'] ?? null;

        return [
            'reached' => true,
            'status' => $status,
            'external_id' => filled($externalId) ? (string) $externalId : null,
            'token' => filled($token) ? (string) $token : null,
            'code' => $code,
            'message' => mb_substr($message, 0, 240),
        ];
    }
}
