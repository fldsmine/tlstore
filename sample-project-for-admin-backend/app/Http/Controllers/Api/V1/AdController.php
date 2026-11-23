<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdResource;
use App\Models\Ad;
use App\Models\AdShare;
use App\Services\AdService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index(Request $request, AdService $ads): JsonResponse
    {
        $user = $request->user();

        $active = $ads->activeAds();
        $earnedIds = $ads->earnedCurrentAdIds($user, $active);

        // Mark per-ad earning state so the mobile client can render "earned".
        $active->each(function (Ad $ad) use ($earnedIds): void {
            $ad->earned_current = in_array($ad->id, $earnedIds, true);
        });

        return ApiResponse::success('Ads retrieved successfully.', [
            'enabled' => $ads->isEnabled(),
            'ads' => AdResource::collection($active),
            'lifetime_usd' => (float) AdShare::query()->where('user_id', $user->id)->sum('amount'),
            'today_usd' => $ads->earnedToday($user),
            'week_usd' => $ads->earnedThisWeek($user),
            'month_usd' => $ads->earnedThisMonth($user),
        ]);
    }

    public function share(Request $request, string $ad, AdService $ads): JsonResponse
    {
        if (! $ads->isEnabled()) {
            return ApiResponse::error('Ads are not available right now.', 403);
        }

        $record = Ad::query()->active()->whereKey($ad)->firstOrFail();

        $amount = $ads->share($request->user(), $record);

        if ($amount <= 0) {
            return ApiResponse::error('You have already earned from this ad.', 422);
        }

        return ApiResponse::success('Ad shared — earning added to your Ads Revenue wallet.', [
            'amount_usd' => round($amount, 6),
        ]);
    }
}
