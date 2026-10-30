<?php

namespace App\Support;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponCountryPolicy
{
    public static function canUse(User $redeemer, Coupon $coupon): bool
    {
        return $redeemer->isNigerian() === ! $coupon->isForeign();
    }

    public static function assertUsable(User $redeemer, Coupon $coupon, string $field = 'code'): void
    {
        if (self::canUse($redeemer, $coupon)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $redeemer->isNigerian()
                ? 'This token was issued by a vendor outside Nigeria and can only be redeemed by an international account. Please use a token from a Nigerian vendor.'
                : 'This token was issued by a Nigerian vendor and can only be redeemed by a Nigerian account. Please use a token from an international vendor.',
        ]);
    }
}
