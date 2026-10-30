<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AgentTransferRequest;
use App\Http\Requests\Api\V1\MintTokensRequest;
use App\Http\Resources\Api\V1\CouponBatchResource;
use App\Http\Resources\Api\V1\CouponResource;
use App\Models\Coupon;
use App\Services\AgentTransferService;
use App\Services\CouponMintingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Vendor / agent area — guarded by the api.agent middleware. */
class AgentController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $coupons = Coupon::query()->where('agent_id', $user->id)->get();

        return ApiResponse::success('Vendor summary retrieved.', [
            'float_balance_usd' => (float) $user->agent_balance,
            'is_special_vendor' => $user->isSpecialVendor(),
            'tokens' => [
                'total' => $coupons->count(),
                'active' => $coupons->whereNull('used_at')->count(),
                'used' => $coupons->whereNotNull('used_at')->count(),
            ],
        ]);
    }

    /** The mintable plan catalogue with this vendor's regional agent pricing. */
    public function plans(Request $request, CouponMintingService $minting): JsonResponse
    {
        return ApiResponse::success('Mintable plans retrieved.', [
            'plans' => $minting->planOptions($request->user()),
        ]);
    }

    public function tokens(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $paginator = Coupon::query()
            ->where('agent_id', $request->user()->id)
            ->with(['package', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return ApiResponse::paginated($paginator, CouponResource::class, 'Tokens retrieved successfully.');
    }

    public function mintTokens(MintTokensRequest $request, CouponMintingService $minting): JsonResponse
    {
        $result = $minting->mint(
            $request->user(),
            (string) $request->validated('plan_key'),
            (int) $request->validated('quantity'),
        );

        $tokens = Coupon::query()
            ->where('batch_id', $result['batch']->id)
            ->with(['package', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->get();

        return ApiResponse::created($result['batch']->quantity.' token(s) generated.', [
            'batch' => new CouponBatchResource($result['batch']),
            'cost_usd' => $result['cost'],
            'tokens' => CouponResource::collection($tokens),
        ]);
    }

    public function batches(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $paginator = $request->user()->couponBatches()
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return ApiResponse::paginated($paginator, CouponBatchResource::class, 'Token batches retrieved successfully.');
    }

    public function transfer(AgentTransferRequest $request, AgentTransferService $transfers): JsonResponse
    {
        $debit = $transfers->transfer(
            $request->user(),
            (string) $request->validated('recipient_username'),
            (float) $request->validated('amount'),
            (string) $request->header('Idempotency-Key', ''),
        );

        return ApiResponse::success('Transfer completed.', [
            'transaction_id' => $debit->id,
            'reference' => $debit->reference,
            'amount_usd' => (float) $debit->amount,
            'float_balance_usd' => (float) $request->user()->refresh()->agent_balance,
        ]);
    }
}
