<?php

namespace App\Features\Vas\Http\Controllers\Api\V1;

use App\Application\Actions\Vas\PurchaseVasOrder;
use App\Application\Money\CurrencyMinorUnits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VasPurchaseController
{
    public function store(Request $request, PurchaseVasOrder $purchase): JsonResponse
    {
        $requiresPin = filled($request->user()->security_pin);
        $data = $request->validate([
            'service' => ['required', 'in:airtime,data,tv,electricity,education'],
            'service_id' => ['required', 'string', 'max:80'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'variation_code' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'meter_type' => ['nullable', 'in:prepaid,postpaid'],
            'subscription_action' => ['nullable', 'in:renew,change'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80'],
            'security_pin' => [$requiresPin ? 'required' : 'nullable', 'digits:4'],
        ]);

        $result = $purchase->handle($request->user(), $data);
        $order = $result['order'];
        $status = $order->status->value;
        $message = match ($status) {
            'delivered' => 'Your purchase was completed.',
            'failed', 'reversed' => 'The purchase failed and the reserved funds have been returned.',
            default => 'Your purchase is being confirmed by VTpass.',
        };

        return response()->json([
            'message' => $message,
            'data' => [
                'id' => $order->id,
                'reference' => $order->request_id,
                'service' => $order->service,
                'service_id' => $order->service_id,
                'provider' => $order->provider,
                'variation_code' => $order->variation_code,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer_name,
                'phone' => $order->phone,
                'amount_ngn_minor' => $order->amount_ngn_minor,
                'charged_amount_minor' => $order->charged_amount_minor,
                'currency' => $order->currency,
                'currency_minor_units' => CurrencyMinorUnits::precision($order->currency),
                'exchange_rate' => (string) $order->exchange_rate,
                'status' => $status,
                'token' => $order->token,
                'refunded' => $order->refunded,
                'meta' => $order->meta,
                'settled_at' => $order->settled_at,
                'created_at' => $order->created_at,
            ],
        ], $result['created'] ? 201 : 200);
    }
}
