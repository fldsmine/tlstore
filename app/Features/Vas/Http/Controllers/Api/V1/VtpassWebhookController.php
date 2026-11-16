<?php

namespace App\Features\Vas\Http\Controllers\Api\V1;

use App\Application\Actions\Vas\ReconcileVasOrder;
use App\Models\VtuOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

final class VtpassWebhookController
{
    public function __invoke(Request $request, ReconcileVasOrder $reconcile): JsonResponse
    {
        $secret = (string) config('services.vtpass.webhook_secret');
        if ($secret === '') {
            return response()->json(['message' => 'VTpass webhook is not configured.'], 503);
        }

        $provided = (string) $request->header('X-Webhook-Secret', '');
        if ($provided === '' || ! hash_equals($secret, $provided)) {
            return response()->json(['message' => 'Webhook authentication failed.'], 401);
        }

        $payload = $request->all();
        $requestId = Arr::get($payload, 'data.requestId')
            ?? Arr::get($payload, 'data.request_id')
            ?? Arr::get($payload, 'requestId')
            ?? Arr::get($payload, 'request_id');
        if (! is_string($requestId) || trim($requestId) === '') {
            return response()->json(['message' => 'The callback did not include a request ID.'], 422);
        }

        $order = VtuOrder::query()->where('request_id', trim($requestId))->first();
        if (! $order) {
            // Acknowledge unknown IDs so VTpass does not retry a callback for an unrelated order.
            return response()->json(['received' => true, 'status' => 'unknown_order']);
        }

        try {
            // Callback fields are only a lookup hint. The server asks VTpass for the authoritative
            // result before changing order or wallet state.
            $order = $reconcile->handle($order);

            return response()->json(['received' => true, 'status' => $order->status->value]);
        } catch (Throwable $exception) {
            Log::error('VTpass webhook reconciliation failed', [
                'request_id' => $requestId,
                'exception' => $exception::class,
            ]);

            return response()->json(['message' => 'Order reconciliation failed.'], 500);
        }
    }
}
