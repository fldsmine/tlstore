<?php

namespace App\Features\Vas\Http\Controllers\Api\V1;

use App\Application\Actions\Vas\ReconcileVasOrder;
use App\Enums\VtuOrderStatus;
use App\Features\Vas\Http\Resources\VasOrderResource;
use App\Models\VtuOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VasOrderController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'service' => ['sometimes', 'in:airtime,data,tv,electricity,education'],
            'status' => ['sometimes', 'in:pending,delivered,failed,reversed'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);
        $orders = VtuOrder::query()
            ->where('user_id', $request->user()->id)
            ->when(isset($filters['service']), fn ($query) => $query->where('service', $filters['service']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return response()->json([
            'data' => $orders->getCollection()->map(fn (VtuOrder $order): array => VasOrderResource::make($order))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, VtuOrder $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json(['data' => VasOrderResource::make($order)]);
    }

    public function check(Request $request, VtuOrder $order, ReconcileVasOrder $reconcile): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        if ($order->status->isPending()) {
            $order = $reconcile->handle($order);
        }

        return response()->json([
            'message' => $this->statusMessage($order->status),
            'data' => VasOrderResource::make($order),
        ]);
    }

    private function statusMessage(VtuOrderStatus $status): string
    {
        return match ($status) {
            VtuOrderStatus::Delivered => 'Your purchase was completed.',
            VtuOrderStatus::Failed, VtuOrderStatus::Reversed => 'The purchase failed and the reserved funds have been returned.',
            VtuOrderStatus::Pending => 'VTpass has not confirmed the purchase yet. Your order remains pending.',
        };
    }
}
