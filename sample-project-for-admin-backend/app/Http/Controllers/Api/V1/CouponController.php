<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerifyCouponRequest;
use App\Http\Resources\Api\V1\CouponVerificationResource;
use App\Models\Coupon;
use App\Services\CouponVerificationLimiter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    /** Public — the Android coupon-search screen checks a code before redemption. */
    public function verify(VerifyCouponRequest $request, CouponVerificationLimiter $limiter): JsonResponse
    {
        if ($limiter->locked($request)) {
            return $this->throttled(
                'Too many invalid coupon codes. Try again in '.$limiter->retryAfter($request).' seconds.',
                $limiter->retryAfter($request),
            );
        }

        if ($limiter->tooFast($request)) {
            return $this->throttled(
                'You can only check one coupon code per minute. Try again in '.$limiter->retryAfter($request).' seconds.',
                $limiter->retryAfter($request),
            );
        }

        $limiter->hitMinute($request);

        $coupon = Coupon::query()
            ->with([
                'package',
                'additionalMinutePlan',
                'cloudStoragePlan',
                'batch.package',
                'batch.additionalMinutePlan',
                'batch.cloudStoragePlan',
                'agent',
                'redeemer',
            ])
            ->where('code', $request->validated('code'))
            ->first();

        if (! $coupon) {
            $limiter->recordMiss($request);

            return response()->json([
                'success' => false,
                'message' => 'Coupon code was not found.',
                'data' => null,
            ], 404);
        }

        $limiter->recordHit($request);

        return ApiResponse::success(
            'Coupon code found.',
            CouponVerificationResource::make($coupon)->resolve($request),
        );
    }

    private function throttled(string $message, int $retryAfter): JsonResponse
    {
        return ApiResponse::error($message, 429)
            ->withHeaders(['Retry-After' => (string) max(1, $retryAfter)]);
    }
}
