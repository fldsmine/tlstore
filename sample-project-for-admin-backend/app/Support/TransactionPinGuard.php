<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate-limited transaction-PIN verification shared by the web (Livewire) and
 * API layers. Same throttle key on both surfaces, so a brute force attempt
 * against one locks the PIN out everywhere.
 */
class TransactionPinGuard
{
    public const int MAX_ATTEMPTS = 5;

    public const int DECAY_SECONDS = 300;

    public function verify(User $user, string $pin): bool
    {
        $key = 'txn-pin:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return false;
        }

        if (! $user->transaction_pin || ! Hash::check($pin, (string) $user->transaction_pin)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return false;
        }

        RateLimiter::clear($key);

        return true;
    }

    public function throttled(User $user): bool
    {
        return RateLimiter::tooManyAttempts('txn-pin:'.$user->id, self::MAX_ATTEMPTS);
    }

    public function availableIn(User $user): int
    {
        return RateLimiter::availableIn('txn-pin:'.$user->id);
    }
}
