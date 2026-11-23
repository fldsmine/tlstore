<?php

namespace App\Services\Vas\Providers;

use App\Contracts\BillPaymentProvider;
use App\Enums\VtuStatus;
use App\Models\VtuOrder;
use App\Services\Vas\CatalogCache;
use App\Services\Vas\DTO\BillFulfilmentResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bill fulfilment via the Flutterwave v3 Bills API. Flutterwave keys on biller_code + item_code, so
 * a pinned order stores the biller_code in service_id and the item_code in variation_code; the
 * catalogue comes from /v3/bills/{category}/billers then /v3/billers/{biller}/items.
 */
class FlutterwaveBillsProvider implements BillPaymentProvider
{
    /** Our service => the Flutterwave bill-category code(s) whose billers it covers. */
    private const CATEGORY = [
        VtuOrder::SERVICE_AIRTIME => ['AIRTIME'],
        VtuOrder::SERVICE_DATA => ['MOBILEDATA', 'INTSERVICE'],
        VtuOrder::SERVICE_TV => ['CABLEBILLS'],
        VtuOrder::SERVICE_ELECTRICITY => ['UTILITYBILLS'],
        VtuOrder::SERVICE_EDUCATION => ['SCHPB'],
    ];

    /** Legacy canonical airtime network id => [biller_code, item_code]. */
    private const AIRTIME_CANONICAL = [
        'mtn' => ['BIL099', 'AT099'],
        'airtel' => ['BIL100', 'AT100'],
        'glo' => ['BIL102', 'AT102'],
        '9mobile' => ['BIL103', 'AT103'],
    ];

    /** Airtime biller_code => its single item_code (airtime is amount-priced, one item per network). */
    private const AIRTIME_ITEM = [
        'BIL099' => 'AT099',
        'BIL100' => 'AT100',
        'BIL102' => 'AT102',
        'BIL103' => 'AT103',
    ];

    /** Preferred biller display name — FLW reuses a biller_code for two names (BIL123 = DAARSAT + STARTIMES). */
    private const BILLER_LABEL = [
        'BIL123' => 'STARTIMES',
    ];

    public function name(): string
    {
        return 'flutterwave';
    }

    public function configured(): bool
    {
        return filled(config('services.flutterwave.secret_key'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.flutterwave.base_url', 'https://api.flutterwave.com'))
            ->withToken((string) config('services.flutterwave.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    private function cacheHours(): int
    {
        return max(1, (int) config('services.vtpass.cache_hours', 6));
    }

    /** Network-level billers for a Flutterwave category, cached. */
    private function billers(string $category): Collection
    {
        $rows = CatalogCache::remember("vtu:flw:billers:{$category}", $this->cacheHours(), fn (): array => (array) $this->client()->get("/v3/bills/{$category}/billers", ['country' => 'NG'])->json('data'));

        return collect($rows);
    }

    public function services(): array
    {
        $services = [];

        foreach (self::CATEGORY as $service => $categories) {
            $services[$service] = [
                'providers' => collect($categories)
                    ->flatMap(fn (string $category): array => $this->billers($category)->all())
                    ->map(function ($row): array {
                        $code = (string) (((array) $row)['biller_code'] ?? '');

                        return [
                            'id' => $code,
                            'label' => self::BILLER_LABEL[$code] ?? (string) (((array) $row)['short_name'] ?? ((array) $row)['name'] ?? ''),
                        ];
                    })
                    ->filter(fn (array $p): bool => $p['id'] !== '')
                    ->unique('id')
                    ->values()
                    ->all(),
            ];
        }

        return $services;
    }

    public function variations(string $service, string $serviceId): array
    {
        $rows = CatalogCache::remember("vtu:flw:items:{$serviceId}", $this->cacheHours(), fn (): array => (array) $this->client()->get("/v3/billers/{$serviceId}/items")->json('data'));

        return collect($rows)
            ->map(fn ($row): array => [
                'code' => (string) (((array) $row)['item_code'] ?? ''),
                'label' => $this->itemLabel((array) $row),
                'amount' => (float) (((array) $row)['amount'] ?? 0),
            ])
            ->filter(fn (array $v): bool => $v['code'] !== '')
            ->unique('code')
            ->values()
            ->all();
    }

    /**
     * The human package name for a bill item. Flutterwave is inconsistent: `name` is the generic
     * biller (STARTIMES, MTN DATA BUNDLE), while the specific package sits in `biller_name` for some
     * billers (STARTIMES → "NOVA - One Day") and `short_name` for others (MTN → "MTN 50 MB"). Pick
     * whichever differs from the generic biller name.
     */
    private function itemLabel(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));
        $biller = trim((string) ($row['biller_name'] ?? ''));
        $short = trim((string) ($row['short_name'] ?? ''));

        if ($biller !== '' && $biller !== $name) {
            return $biller;
        }

        if ($short !== '' && $short !== $name) {
            return $short;
        }

        return $short ?: ($biller ?: $name);
    }

    public function validateCustomer(string $service, string $serviceId, string $customerId, ?string $type = null): array
    {
        // Flutterwave validates the customer against an item_code — resolve the best one for this service.
        $item = $this->validationItem($service, $serviceId, $type);

        if ($item === '') {
            return ['ok' => false, 'name' => '', 'message' => 'Choose a plan first.', 'data' => []];
        }

        try {
            $response = $this->client()->get("/v3/bill-items/{$item}/validate", [
                'code' => $serviceId,
                'customer' => $customerId,
            ]);

            $name = (string) ($response->json('data.name') ?? '');

            return [
                'ok' => ! $response->failed() && $response->json('status') === 'success' && filled($name),
                'name' => $name,
                'message' => (string) $response->json('message', ''),
                'data' => (array) $response->json('data'),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'name' => '', 'message' => 'Could not verify this customer right now.', 'data' => []];
        }
    }

    /**
     * The item_code to validate a customer against. TV/education pass the chosen item directly;
     * electricity passes a meter type (resolve the disco's item); anything else falls back to the
     * biller's first item — any item validates the same customer.
     */
    private function validationItem(string $service, string $serviceId, ?string $type): string
    {
        if ($service === VtuOrder::SERVICE_AIRTIME) {
            return self::AIRTIME_CANONICAL[strtolower($serviceId)][1] ?? self::AIRTIME_ITEM[$serviceId] ?? '';
        }

        if (! str_starts_with($serviceId, 'BIL')) {
            return (string) $type;
        }

        $items = $this->variations($service, $serviceId);

        if ($type !== null && $type !== '' && in_array($type, array_column($items, 'code'), true)) {
            return $type;
        }

        if ($service === VtuOrder::SERVICE_ELECTRICITY) {
            $meter = strtolower((string) $type) ?: 'prepaid';
            $match = collect($items)->first(fn (array $i): bool => str_contains(strtolower($i['label']), $meter));

            return (string) ($match['code'] ?? ($items[0]['code'] ?? ''));
        }

        return (string) ($items[0]['code'] ?? '');
    }

    public function pay(VtuOrder $order): BillFulfilmentResult
    {
        $codes = $this->resolveCodes($order);

        if ($codes === null) {
            return new BillFulfilmentResult(reached: true, status: VtuStatus::FAILED, message: 'This bill is not available on Flutterwave yet.');
        }

        [$biller, $item] = $codes;
        $customer = (string) ($order->billers_code ?: $order->phone);

        try {
            $response = $this->client()->post("/v3/billers/{$biller}/items/{$item}/payment", [
                'country' => 'NG',
                'customer_id' => $customer,
                'amount' => (float) $order->amount_ngn,
                'reference' => $order->request_id,
            ]);

            // No definitive answer (5xx / rate limit) — leave pending; the bill may still be placed.
            if ($response->serverError() || $response->status() === 429) {
                return BillFulfilmentResult::unreachable('Flutterwave is temporarily unavailable.');
            }

            // A definitive non-success (rejected biller/amount/customer, before any charge) → refund.
            if ($response->json('status') !== 'success') {
                return new BillFulfilmentResult(reached: true, status: VtuStatus::FAILED, raw: (array) $response->json(), message: (string) $response->json('message', 'Bill payment failed'));
            }

            $data = (array) $response->json('data');

            // A successful create only ACCEPTS the bill — Flutterwave fulfils asynchronously and
            // confirms via webhook/status, so the order stays pending until requery resolves it.
            return new BillFulfilmentResult(
                reached: true,
                status: VtuStatus::PENDING,
                externalId: $this->externalId($data),
                token: $this->token($data),
                raw: (array) $response->json(),
                message: (string) $response->json('message', ''),
            );
        } catch (Throwable $e) {
            Log::error('Flutterwave bill pay failed', ['reference' => $order->request_id, 'message' => $e->getMessage()]);

            return BillFulfilmentResult::unreachable('Unable to reach Flutterwave.');
        }
    }

    public function requery(VtuOrder $order): BillFulfilmentResult
    {
        try {
            $response = $this->client()->get('/v3/bills/'.rawurlencode($order->request_id), ['verbose' => 1]);

            if ($response->failed() || $response->json('status') !== 'success') {
                return BillFulfilmentResult::unreachable();
            }

            $data = (array) $response->json('data');

            return new BillFulfilmentResult(
                reached: true,
                status: self::resolveStatus((string) ($data['status'] ?? 'pending')),
                externalId: $this->externalId($data),
                token: $this->token($data),
                raw: (array) $response->json(),
            );
        } catch (Throwable $e) {
            return BillFulfilmentResult::unreachable();
        }
    }

    /**
     * Resolve the Flutterwave biller_code + item_code for an order.
     *
     * @return array{0: string, 1: string}|null
     */
    private function resolveCodes(VtuOrder $order): ?array
    {
        $serviceId = (string) $order->service_id;

        switch ($order->service) {
            case VtuOrder::SERVICE_AIRTIME:
                // canonical network id (mtn) or a native airtime biller (BIL099) — both amount-priced.
                return self::AIRTIME_CANONICAL[strtolower($serviceId)]
                    ?? (isset(self::AIRTIME_ITEM[$serviceId]) ? [$serviceId, self::AIRTIME_ITEM[$serviceId]] : null);

            case VtuOrder::SERVICE_ELECTRICITY:
                // amount-priced: variation_code carries the meter type — resolve the disco's item.
                if (! str_starts_with($serviceId, 'BIL')) {
                    return null;
                }
                $meter = strtolower((string) $order->variation_code) ?: 'prepaid';
                $items = $this->variations($order->service, $serviceId);
                $item = collect($items)->first(fn (array $i): bool => str_contains(strtolower($i['label']), $meter)) ?? ($items[0] ?? null);

                return $item ? [$serviceId, $item['code']] : null;

            default:
                // data / tv: biller + the item selected in the UI.
                return (str_starts_with($serviceId, 'BIL') && filled($order->variation_code)) ? [$serviceId, (string) $order->variation_code] : null;
        }
    }

    private function externalId(array $data): ?string
    {
        $id = $data['flw_ref'] ?? $data['tx_ref'] ?? $data['reference'] ?? null;

        return filled($id) ? (string) $id : null;
    }

    private function token(array $data): ?string
    {
        $token = $data['recharge_token'] ?? $data['token'] ?? (is_array($data['extra'] ?? null) ? ($data['extra']['token'] ?? null) : ($data['extra'] ?? null));

        return filled($token) ? (string) $token : null;
    }

    private static function resolveStatus(string $status): VtuStatus
    {
        return match (strtolower($status)) {
            'successful', 'success', 'completed', 'delivered' => VtuStatus::DELIVERED,
            'failed', 'cancelled', 'canceled' => VtuStatus::FAILED,
            'reversed', 'refunded' => VtuStatus::REVERSED,
            default => VtuStatus::PENDING,
        };
    }
}
