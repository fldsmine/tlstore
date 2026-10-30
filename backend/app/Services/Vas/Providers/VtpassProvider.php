<?php

namespace App\Services\Vas\Providers;

use App\Contracts\BillPaymentProvider;
use App\Enums\VtuStatus;
use App\Models\VtuOrder;
use App\Services\Vas\DTO\BillFulfilmentResult;
use App\Services\Vas\VtpassClient;
use App\Services\Vas\VtuCatalog;
use Illuminate\Support\Arr;

class VtpassProvider implements BillPaymentProvider
{
    public function __construct(private VtpassClient $client) {}

    public function name(): string
    {
        return 'vtpass';
    }

    public function configured(): bool
    {
        return filled(config('services.vtpass.api_key')) && filled(config('services.vtpass.secret_key'));
    }

    public function services(): array
    {
        return collect(VtuCatalog::categories())
            ->mapWithKeys(fn (array $category, string $key): array => [$key => ['providers' => $category['providers'] ?? []]])
            ->all();
    }

    public function variations(string $service, string $serviceId): array
    {
        return VtuCatalog::variations($serviceId);
    }

    public function validateCustomer(string $service, string $serviceId, string $customerId, ?string $type = null): array
    {
        // VTpass merchant-verify skips `type` only for TV (smartcard alone); electricity (meter) and education (JAMB variation) need it.
        $vtpassType = $service === VtuOrder::SERVICE_TV ? null : $type;

        $response = $this->client->verify($serviceId, $customerId, $vtpassType);

        $name = (string) (Arr::get($response, 'data.Customer_Name') ?? Arr::get($response, 'data.customerName') ?? '');

        return [
            'ok' => $response['ok'] && filled($name),
            'name' => $name,
            'message' => (string) ($response['message'] ?? ''),
            'data' => (array) ($response['data'] ?? []),
        ];
    }

    public function pay(VtuOrder $order): BillFulfilmentResult
    {
        return $this->toResult($this->client->pay($this->buildPayload($order)));
    }

    public function requery(VtuOrder $order): BillFulfilmentResult
    {
        return $this->toResult($this->client->requery($order->request_id));
    }

    private function toResult(array $response): BillFulfilmentResult
    {
        $code = (string) ($response['code'] ?? 'error');

        if ($code === 'error') {
            return BillFulfilmentResult::unreachable((string) ($response['message'] ?? ''));
        }

        return new BillFulfilmentResult(
            reached: true,
            status: VtuStatus::fromVtpass($code, $response['status'] ?? null),
            externalId: Arr::get($response, 'transaction.transactionId') !== null ? (string) Arr::get($response, 'transaction.transactionId') : null,
            token: $this->extractToken($response),
            raw: (array) ($response['raw'] ?? []),
            message: (string) ($response['message'] ?? ''),
        );
    }

    private function buildPayload(VtuOrder $order): array
    {
        $payload = [
            'request_id' => $order->request_id,
            'serviceID' => $order->service_id,
            'phone' => $order->phone,
        ];

        if ($order->variation_code) {
            $payload['variation_code'] = $order->variation_code;
        }

        if ($order->billers_code) {
            $payload['billersCode'] = $order->billers_code;
        }

        $payload['amount'] = (float) $order->amount_ngn;

        $extra = (array) ($order->meta['extra'] ?? []);

        return array_merge($extra, $payload);
    }

    private function extractToken(array $response): ?string
    {
        $token = Arr::get($response, 'transaction.purchased_code')
            ?? Arr::get($response, 'transaction.token')
            ?? Arr::get($response, 'data.token');

        return filled($token) ? (string) $token : null;
    }
}
