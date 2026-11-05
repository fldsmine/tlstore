<?php

namespace App\Features\SocialFollow\Http\Controllers\Api\V1;

use App\Application\Actions\SocialFollow\ClaimSocialFollowReward;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\SocialHandle;
use App\Models\UserSocialFollow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SocialFollowController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $follows = UserSocialFollow::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('social_handle_id');

        $handles = SocialHandle::query()->active()->get()->map(function (SocialHandle $handle) use ($follows): array {
            $follow = $follows->get($handle->id);

            return [
                'id' => $handle->id,
                'key' => $handle->handle_key,
                'name' => $handle->name,
                'channel' => $handle->channel_url,
                'icon' => $handle->icon,
                'reward_amount_minor' => $handle->reward_amount_minor,
                'reward_amount' => CurrencyMinorUnits::fromMinor($handle->reward_amount_minor, $handle->currency),
                'currency' => $handle->currency,
                'currency_minor_units' => CurrencyMinorUnits::precision($handle->currency),
                'followed' => $follow !== null && $follow->status === 'completed',
                'followed_at' => $follow?->followed_at?->toISOString(),
            ];
        })->values();

        return response()->json(['data' => $handles]);
    }

    public function follow(
        Request $request,
        SocialHandle $socialHandle,
        ClaimSocialFollowReward $claim,
    ): JsonResponse {
        $follow = $claim->handle($request->user(), $socialHandle);

        return response()->json([
            'message' => $follow->wasRecentlyCreated
                ? 'Your follow was recorded and the reward was added to your bonus wallet.'
                : 'You have already claimed this social follow reward.',
            'data' => [
                'id' => $follow->id,
                'social_handle_id' => $follow->social_handle_id,
                'status' => $follow->status,
                'reward_amount_minor' => $follow->reward_amount_minor,
                'currency' => $follow->currency,
                'already_completed' => ! $follow->wasRecentlyCreated,
                'followed_at' => $follow->followed_at?->toISOString(),
            ],
        ]);
    }
}
