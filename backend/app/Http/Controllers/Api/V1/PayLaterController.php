<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PayLaterApplyRequest;
use App\Http\Requests\Api\V1\PayLaterRepayRequest;
use App\Http\Resources\Api\V1\MinuteCreditResource;
use App\Models\AdditionalMinutePlan;
use App\Services\PayLaterService;
use App\Services\TokenRedemptionService;
use App\Support\ApiResponse;
use App\Support\TransactionPinGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayLaterController extends Controller
{
    public function index(Request $request, PayLaterService $payLater): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success('Pay Later status retrieved.', [
            'enabled' => $payLater->isEnabled(),
            'eligible' => $payLater->isEligible($user),
            'has_outstanding' => $payLater->hasOutstanding($user),
            'credits' => MinuteCreditResource::collection($user->minuteCredits()->latest()->take(15)->get()),
        ]);
    }

    public function apply(PayLaterApplyRequest $request, PayLaterService $payLater, TransactionPinGuard $pins): JsonResponse
    {
        $user = $request->user();

        if (! $payLater->isEligible($user)) {
            return ApiResponse::error('Pay Later isn’t available on your account right now.', 403);
        }

        $plan = AdditionalMinutePlan::query()->findOrFail($request->validated('plan_id'));

        if ($pins->throttled($user)) {
            return ApiResponse::error('Too many incorrect attempts. Try again in '.$pins->availableIn($user).' seconds.', 429)
                ->withHeaders(['Retry-After' => (string) $pins->availableIn($user)]);
        }

        if (! $pins->verify($user, (string) $request->validated('pin'))) {
            return ApiResponse::error('Your transaction PIN is incorrect.', 422, [
                'pin' => ['Your transaction PIN is incorrect.'],
            ]);
        }

        $credit = $payLater->apply($user, $plan);

        return ApiResponse::created('Minutes activated — start earning immediately.', [
            'credit' => new MinuteCreditResource($credit),
        ]);
    }

    public function repay(PayLaterRepayRequest $request, string $credit, TokenRedemptionService $redemption): JsonResponse
    {
        $user = $request->user();

        $record = $user->minuteCredits()->outstanding()->whereKey($credit)->firstOrFail();

        $redemption->repayPayLater($user, $record, (string) $request->validated('token'));

        return ApiResponse::success('Pay Later repaid — you’re all settled.', [
            'credit' => new MinuteCreditResource($record->refresh()),
        ]);
    }
}
