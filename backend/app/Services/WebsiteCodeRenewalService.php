<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\User;
use App\Support\CouponCountryPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Redeems a vendor-minted website-renewal code (the "code" renewal mode). Extends the user's
 * personalized-website window by the configured duration, mirroring the online gateway renewal.
 */
class WebsiteCodeRenewalService
{
    public function redeem(User $user, string $code, string $field = 'code'): void
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->where('type', CouponType::WEBSITE_RENEWAL->value)
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([$field => 'That code isn\'t valid.']);
        }

        if ($coupon->used_at) {
            throw ValidationException::withMessages([$field => 'That code has already been used.']);
        }

        // Renewal is only for an already-activated site — a first website window is stamped at activation.
        if ($user->website_expires_at === null) {
            throw ValidationException::withMessages([$field => 'Activate your eSIM first — renewal is for an existing website.']);
        }

        CouponCountryPolicy::assertUsable($user, $coupon, $field);

        DB::transaction(function () use ($coupon, $user, $field): void {
            if (! $coupon->claim($user)) {
                throw ValidationException::withMessages([$field => 'That code has already been used.']);
            }

            $days = (int) config('settings.website.duration_days');

            User::whereKey($user->id)->lockForUpdate()->first()->extendWebsite($days);
        });
    }
}
