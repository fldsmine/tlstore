<?php

namespace App\Services\Vas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class VtpassClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.vtpass.base_url', 'https://sandbox.vtpass.com'), '/');
    }

    public function pay(array $payload): array
    {
        return $this->post('api/pay', $payload);
    }

    public function requery(string $requestId): array
    {
        return $this->post('api/requery', ['request_id' => $requestId]);
    }

    public function verify(string $serviceID, string $billersCode, ?string $type = null): array
    {
        $payload = ['serviceID' => $serviceID, 'billersCode' => $billersCode];

        if ($type !== null) {
            $payload['type'] = $type;
        }

        return $this->post('api/merchant-verify', $payload);
    }

    public function variations(string $serviceID): array
    {
        try {
            $response = $this->client(secure: false)
                ->get('api/service-variations', ['serviceID' => $serviceID]);

            $json = (array) $response->json();
            $content = (array) ($json['content'] ?? []);

            return [
                'ok' => ! $response->failed() && isset($json['content']['variations']),
                'variations' => (array) ($content['variations'] ?? []),
                'data' => $content,
                'raw' => $json,
                'message' => (string) ($json['response_description'] ?? ''),
            ];
        } catch (Throwable $e) {
            Log::error('VTpass variations failed', ['serviceID' => $serviceID, 'message' => $e->getMessage()]);

            return ['ok' => false, 'variations' => [], 'data' => [], 'raw' => [], 'message' => 'Could not load plans. Please try again.'];
        }
    }

    private function post(string $uri, array $payload): array
    {
        try {
            $response = $this->client(secure: true)->asForm()->post($uri, $payload);
            $json = (array) $response->json();

            $code = (string) ($json['code'] ?? 'error');
            $transaction = (array) (data_get($json, 'content.transactions') ?? []);
            $status = isset($transaction['status']) ? (string) $transaction['status'] : null;

            return [
                'ok' => $code === '000',
                'code' => $code,
                'status' => $status,
                'transaction' => $transaction,
                'data' => (array) ($json['content'] ?? []),
                'message' => (string) ($json['response_description'] ?? ''),
                'raw' => $json,
            ];
        } catch (Throwable $e) {
            Log::error('VTpass request failed', ['uri' => $uri, 'message' => $e->getMessage()]);

            return [
                'ok' => false,
                'code' => 'error',
                'status' => null,
                'transaction' => [],
                'data' => [],
                'message' => 'VTpass is temporarily unavailable. Please try again.',
                'raw' => [],
            ];
        }
    }

    private function client(bool $secure): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(30)
            ->withHeaders([
                'api-key' => (string) config('services.vtpass.api_key'),
                $secure ? 'secret-key' : 'public-key' => (string) config($secure ? 'services.vtpass.secret_key' : 'services.vtpass.public_key'),
            ]);
    }
}
