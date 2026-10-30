<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Coupon lookup throttle: one check per minute, and three misses lock
 * the client out for an hour.
 */
class CouponVerificationLimiter
{
    public const int PER_MINUTE = 1;

    public const int MINUTE_DECAY_SECONDS = 20;

    public const int MAX_MISSES = 3;

    public const int LOCKOUT_SECONDS = 3600;

    public function clientKey(Request $request): string
    {
        $user = $request->user('sanctum');

        return $user
            ? 'user:'.$user->getAuthIdentifier()
            : 'ip:'.$request->ip();
    }

    public function minuteKey(Request $request): string
    {
        return 'coupon-verify-minute:'.$this->clientKey($request);
    }

    public function missKey(Request $request): string
    {
        return 'coupon-verify-miss:'.$this->clientKey($request);
    }

    public function lockKey(Request $request): string
    {
        return 'coupon-verify-lock:'.$this->clientKey($request);
    }

    public function locked(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->lockKey($request), 1);
    }

    public function tooFast(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->minuteKey($request), self::PER_MINUTE);
    }

    public function hitMinute(Request $request): void
    {
        RateLimiter::hit($this->minuteKey($request), self::MINUTE_DECAY_SECONDS);
    }

    public function recordMiss(Request $request): void
    {
        $missKey = $this->missKey($request);
        RateLimiter::hit($missKey, self::LOCKOUT_SECONDS);

        if (RateLimiter::attempts($missKey) >= self::MAX_MISSES) {
            RateLimiter::hit($this->lockKey($request), self::LOCKOUT_SECONDS);
        }
    }

    public function recordHit(Request $request): void
    {
        RateLimiter::clear($this->missKey($request));
    }

    public function retryAfter(Request $request): int
    {
        if ($this->locked($request)) {
            return RateLimiter::availableIn($this->lockKey($request));
        }

        return RateLimiter::availableIn($this->minuteKey($request));
    }

    public function clear(Request $request): void
    {
        RateLimiter::clear($this->minuteKey($request));
        RateLimiter::clear($this->missKey($request));
        RateLimiter::clear($this->lockKey($request));
    }
}
