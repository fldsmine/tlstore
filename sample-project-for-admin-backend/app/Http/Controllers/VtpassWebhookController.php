<?php

namespace App\Http\Controllers;

use App\Models\VtuOrder;
use App\Services\Vas\VtuPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class VtpassWebhookController extends Controller
{
    public function __invoke(Request $request, VtuPurchaseService $service): JsonResponse
    {

        $secret = (string) config('services.vtpass.webhook_secret');
        if ($secret !== '') {
            $given = (string) ($request->header('X-Webhook-Secret') ?? $request->query('secret', ''));
            if (! hash_equals($secret, $given)) {
                return response()->json(['response' => 'rejected'], 401);
            }
        }

        $payload = $request->all();

        $requestId = Arr::get($payload, 'data.requestId')
            ?? Arr::get($payload, 'data.request_id')
            ?? Arr::get($payload, 'requestId')
            ?? Arr::get($payload, 'request_id');

        if (blank($requestId)) {
            return response()->json(['response' => 'ignored', 'reason' => 'no request_id'], 200);
        }

        $order = VtuOrder::query()->where('request_id', $requestId)->first();
        if ($order === null) {
            return response()->json(['response' => 'ignored', 'reason' => 'unknown order'], 200);
        }

        try {

            $fresh = $service->requery($order);

            return response()->json(['response' => 'success', 'status' => $fresh->status->value], 200);
        } catch (\Throwable $e) {
            Log::error('VTpass webhook processing failed', ['request_id' => $requestId, 'message' => $e->getMessage()]);

            return response()->json(['response' => 'error'], 200);
        }
    }
}
