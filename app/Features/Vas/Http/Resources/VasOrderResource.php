<?php

namespace App\Features\Vas\Http\Resources;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\VtuOrder;

final class VasOrderResource
{
    /** @return array<string,mixed> */
    public static function make(VtuOrder $order): array
    {
        $meta = (array) $order->meta;

        return [
            'id' => $order->id,
            'reference' => $order->request_id,
            'service' => $order->service,
            'service_name' => $meta['service_label'] ?? ucfirst($order->service),
            'service_id' => $order->service_id,
            'provider' => $order->provider,
            'provider_name' => $meta['provider_label'] ?? $order->service_id,
            'variation_code' => $order->variation_code,
            'plan_name' => $meta['plan_label'] ?? null,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer_name,
            'phone' => $order->phone,
            'amount_ngn_minor' => $order->amount_ngn_minor,
            'charged_amount_minor' => $order->charged_amount_minor,
            'currency' => $order->currency,
            'currency_minor_units' => CurrencyMinorUnits::precision($order->currency),
            'exchange_rate' => (string) $order->exchange_rate,
            'status' => $order->status->value,
            'token' => $order->token,
            'refunded' => $order->refunded,
            'provider_message' => data_get($meta, 'vtpass.message'),
            'created_at' => $order->created_at,
            'settled_at' => $order->settled_at,
        ];
    }
}
