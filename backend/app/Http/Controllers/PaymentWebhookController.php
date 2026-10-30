<?php

namespace App\Http\Controllers;

use App\Enums\PaymentProvider;
use App\Services\Payments\ChargeSettlement;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One webhook endpoint for every gateway: the {provider} segment selects the SIGNATURE verifier,
 * then settlement re-derives the type + originating driver from the transaction row and
 * re-verifies server-side before crediting — so a webhook signed by provider A can never settle a
 * charge that belongs to provider B.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private ChargeSettlement $settlement,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        if (! in_array($provider, PaymentProvider::values(), true)) {
            abort(404);
        }

        $gateway = $this->gateways->driver($provider);

        if (! $gateway->verifyWebhookSignature($request)) {
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $event = $gateway->parseWebhook($request);

        if ($event === null || $event->reference === '') {
            return response()->json(['status' => 'ignored']);
        }

        try {
            $this->settlement->finalize($event->reference);
        } catch (Throwable $e) {
            Log::error('Payment webhook failed', ['provider' => $provider, 'reference' => $event->reference, 'message' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }
}
