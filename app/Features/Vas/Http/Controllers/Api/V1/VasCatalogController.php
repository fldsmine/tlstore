<?php

namespace App\Features\Vas\Http\Controllers\Api\V1;

use App\Features\Vas\Application\VasCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VasCatalogController
{
    public function index(VasCatalogService $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->catalog()]);
    }

    public function plans(Request $request, VasCatalogService $catalog, string $service): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'string', 'max:80'],
        ]);

        return response()->json(['data' => [
            'service' => $service,
            'service_id' => $data['service_id'],
            'currency' => 'NGN',
            'plans' => $catalog->plans($service, $data['service_id']),
        ]]);
    }

    public function verify(Request $request, VasCatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', 'in:tv,electricity,education'],
            'service_id' => ['required', 'string', 'max:80'],
            'customer_id' => ['required', 'string', 'max:191'],
            'type' => ['nullable', 'string', 'max:80'],
        ]);

        $result = $catalog->verify(
            $data['service'],
            $data['service_id'],
            $data['customer_id'],
            $data['type'] ?? null,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['data' => [
            'verified' => true,
            'customer_name' => $result['customer_name'],
        ]]);
    }
}
