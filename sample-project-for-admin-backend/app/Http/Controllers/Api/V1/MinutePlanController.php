<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RedeemMinutesRequest;
use App\Http\Resources\Api\V1\MinutePlanResource;
use App\Http\Resources\Api\V1\MinuteTopUpResource;
use App\Models\AdditionalMinutePlan;
use App\Services\ClaimService;
use App\Services\TokenRedemptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MinutePlanController extends Controller
{
    public function index(Request $request, ClaimService $claims): JsonResponse
    {
        $user = $request->user()->loadMissing('package');

        $shareSubscription = $claims->subscription($user);
        $plans = AdditionalMinutePlan::query()->active()->ordered()->get();
        $activeTopUps = $user->minuteTopUps()->activeOrShareActive()->with(['plan', 'shareSubscription'])->latest()->get();
        $bonusMinutes = (float) $user->minuteTopUps()->active()->sum('daily_call_minutes');

        return ApiResponse::success('Minutes plans retrieved successfully.', [
            'enabled' => (bool) config('settings.minutes.enabled', false),
            'plans' => MinutePlanResource::collection($plans),
            'base_minutes' => (float) ($user->package?->daily_call_minutes ?? 0), // legacy eSIM daily allowance
            'bonus_minutes' => $bonusMinutes,
            'share_term_allocated_minutes' => $claims->allocatedMinutes($user, $shareSubscription),
            'share_minutes_remaining' => $claims->remainingMinutes($user, $shareSubscription),
            'share_minutes_available_now' => $claims->availableMinutes($user, $shareSubscription),
            'share_plan_expires_at' => $shareSubscription?->expires_at?->toIso8601String(),
            'active_top_ups' => MinuteTopUpResource::collection($activeTopUps),
        ]);
    }

    public function redeem(RedeemMinutesRequest $request, TokenRedemptionService $redemption): JsonResponse
    {
        $topUp = $redemption->redeemMinutes(
            $request->user(),
            (string) $request->validated('plan_id'),
            (string) $request->validated('token'),
        )->load(['plan', 'shareSubscription']);

        return ApiResponse::success(($topUp->plan?->name ?? 'Your plan').' is now active on your account.', [
            'top_up' => new MinuteTopUpResource($topUp),
        ]);
    }
}
