<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RewardTargetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RewardTargetRequest;
use App\Http\Resources\Api\V1\RewardTargetResource;
use App\Models\RewardTarget;
use App\Services\TargetRewardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    public function index(Request $request, TargetRewardService $rewards): JsonResponse
    {
        $user = $request->user();

        $rewards->evaluate($user);

        $today = $rewards->todayTarget($user);
        if ($today) {
            $today->progress = $rewards->progress($user, $today);
        }

        $history = $user->rewardTargets()->latest('date')->take(30)->get();

        return ApiResponse::success('Rewards retrieved successfully.', [
            'enabled' => $rewards->isEnabled(),
            'today_target' => $today ? new RewardTargetResource($today) : null,
            'history' => RewardTargetResource::collection($history),
            'lifetime_earned_usd' => $rewards->lifetimeEarned($user),
            'streak' => $rewards->streak($user),
            'min_target_usd' => $rewards->minTarget(),
            'max_target_usd' => $rewards->maxTarget(),
            'reward_percent' => $rewards->rewardPercent(),
        ]);
    }

    public function setTarget(RewardTargetRequest $request, TargetRewardService $rewards): JsonResponse
    {
        $user = $request->user();

        if (! $rewards->isEnabled()) {
            return ApiResponse::error('Rewards aren’t available right now.', 403);
        }

        if ($rewards->todayTarget($user)) {
            return ApiResponse::error('You’ve already set today’s target — it can’t be changed.', 422);
        }

        $amount = (float) $request->validated('amount');
        $min = $rewards->minTarget();
        $max = $rewards->maxTarget();

        if ($min > 0 && $amount < $min) {
            return ApiResponse::error('The minimum daily target is '.$min.' USD.', 422, [
                'amount' => ['The minimum daily target is '.$min.' USD.'],
            ]);
        }

        if ($max > 0 && $amount > $max) {
            return ApiResponse::error('The maximum daily target is '.$max.' USD.', 422, [
                'amount' => ['The maximum daily target is '.$max.' USD.'],
            ]);
        }

        $target = $rewards->setTarget($user, $amount);

        return ApiResponse::created('Daily target set.', [
            'target' => new RewardTargetResource($target),
        ]);
    }

    public function claim(Request $request, string $target, TargetRewardService $rewards): JsonResponse
    {
        $record = $request->user()->rewardTargets()->whereKey($target)->firstOrFail();

        if ($record->status !== RewardTargetStatus::ACHIEVED) {
            return ApiResponse::error('This target hasn’t been achieved yet.', 422);
        }

        if (! $rewards->claim($record)) {
            return ApiResponse::error('This reward has already been claimed.', 422);
        }

        return ApiResponse::success('Reward claimed to your Target Reward wallet.', [
            'amount_usd' => (float) $record->reward_amount,
        ]);
    }

    public function claimAll(Request $request, TargetRewardService $rewards): JsonResponse
    {
        $total = $rewards->claimAll($request->user());

        return ApiResponse::success(
            $total > 0 ? 'Rewards claimed to your Target Reward wallet.' : 'Nothing to claim right now.',
            ['amount_usd' => round($total, 6)],
        );
    }
}
