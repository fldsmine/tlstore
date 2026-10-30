<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WebsiteCodeRequest;
use App\Services\Payments\WebsiteRenewalService;
use App\Services\WebsiteCodeRenewalService;
use App\Support\ApiResponse;
use App\Support\RegionPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success('Website status retrieved.', [
            'expires_at' => $user->website_expires_at?->toIso8601String(),
            'active' => $user->websiteActive(),
            'renewal_mode' => (string) config('settings.website.renewal_mode', 'online'),
            'renewal_price_usd' => (float) config('settings.website.renewal_price', 1),
            'code_price_usd' => RegionPricing::websiteRenewalCustomer(RegionPricing::forUser($user)),
            'duration_days' => (int) config('settings.website.duration_days', 30),
        ]);
    }

    public function renew(Request $request, WebsiteRenewalService $renewals): JsonResponse
    {
        if ((string) config('settings.website.renewal_mode', 'online') !== 'online') {
            return ApiResponse::error('Online renewal is not enabled — redeem a renewal code instead.', 422);
        }

        $result = $renewals->start($request->user());

        if (! $result['ok']) {
            return ApiResponse::error($result['error'] ?? 'Could not start the renewal.', 422);
        }

        return ApiResponse::success('Renewal started. Complete the payment in the checkout.', [
            'checkout_url' => $result['checkout_url'],
            'reference' => $result['reference'],
        ]);
    }

    public function redeemCode(WebsiteCodeRequest $request, WebsiteCodeRenewalService $renewals): JsonResponse
    {
        $renewals->redeem($request->user(), (string) $request->validated('code'));

        return ApiResponse::success('Website renewed successfully.', [
            'expires_at' => $request->user()->refresh()->website_expires_at?->toIso8601String(),
        ]);
    }
}
