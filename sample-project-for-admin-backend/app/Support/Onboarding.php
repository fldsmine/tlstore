<?php

namespace App\Support;

use App\Models\User;

class Onboarding
{
    /**
     * The route name of the next onboarding step the user must complete,
     * or null once they are fully onboarded. This is the single source of
     * truth for onboarding order — verify → PIN → dashboard. Package activation is an optional eSIM step.
     */
    public static function nextRoute(User $user): ?string
    {
        if (config('settings.app.enable_email_verification') && ! $user->email_verified_at) {
            return 'auth.verify-email';
        }

        if (empty($user->transaction_pin)) {
            return 'auth.setup-pin';
        }

        return null;
    }
}
