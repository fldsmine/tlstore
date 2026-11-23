<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VtuOrderResource;
use App\Models\VtuOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VtuOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'service' => ['nullable', Rule::in([
                VtuOrder::SERVICE_AIRTIME,
                VtuOrder::SERVICE_DATA,
                VtuOrder::SERVICE_TV,
                VtuOrder::SERVICE_ELECTRICITY,
                VtuOrder::SERVICE_EDUCATION,
            ])],
            'status' => ['nullable', Rule::in(['pending', 'delivered', 'failed', 'reversed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $request->user()->vtuOrders()
            ->when($request->input('service'), fn ($q, $service) => $q->where('service', $service))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return ApiResponse::paginated($paginator, VtuOrderResource::class, 'Bill payments retrieved successfully.');
    }

    public function show(Request $request, string $order): JsonResponse
    {
        // Orders are addressable by internal id or by the public request_id.
        $record = $request->user()->vtuOrders()
            ->where(fn ($q) => $q->whereKey($order)->orWhere('request_id', $order))
            ->firstOrFail();

        return ApiResponse::success('Bill payment retrieved successfully.', [
            'order' => new VtuOrderResource($record),
        ]);
    }
}
