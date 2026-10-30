<?php

namespace App\Services\Auth;

use App\Enums\LoginChannel;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserLoginEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared credential-resolution + throttling rules for the web login (Livewire)
 * and the API login. Extracted from App\Livewire\Auth\Login.
 */
class LoginService
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    /** Users may sign in with their email or their username. */
    public static function credentialField(string $login): string
    {
        return filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    public static function throttleKey(string $login, string $ip): string
    {
        return 'login:'.Str::lower($login).'|'.$ip;
    }

    public function throttled(string $login, string $ip): bool
    {
        return RateLimiter::tooManyAttempts(self::throttleKey($login, $ip), self::MAX_ATTEMPTS);
    }

    public function availableIn(string $login, string $ip): int
    {
        return RateLimiter::availableIn(self::throttleKey($login, $ip));
    }

    public function isSuspended(User $user): bool
    {
        return $user->status === UserStatus::SUSPENDED;
    }

    /**
     * Resolve the user matching the given credentials, or null on mismatch.
     * A failure hits the throttle; a credential match clears it. Status is NOT
     * checked here — callers decide how to surface a suspended account.
     */
    public function attempt(string $login, string $password): ?User
    {
        $key = self::throttleKey($login, request()->ip());

        $user = User::query()
            ->where(self::credentialField($login), $login)
            ->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return null;
        }

        RateLimiter::clear($key);

        return $user;
    }

    /**
     * Stamp the login time once the session/token is actually established, and append the
     * event the backoffice sign-in report reads.
     *
     * `last_login_at` alone is an overwrite, so it cannot answer "who signed in today" for
     * any past day and loses every repeat sign-in; every caller therefore also records the
     * event. Both writes happen together so the profile and the log never disagree.
     */
    public function touchLastLogin(User $user, LoginChannel|string $channel = LoginChannel::WEB): void
    {
        $now = now();
        $channel = $channel instanceof LoginChannel
            ? $channel
            : (LoginChannel::tryFrom($channel) ?? LoginChannel::WEB);

        $user->forceFill(['last_login_at' => $now])->save();

        $this->recordLoginEvent($user, $channel, $now);
    }

    /**
     * Sign-in bookkeeping must never be able to block a login, so a failure here is logged
     * and swallowed — the user is already authenticated by the time we get here.
     */
    protected function recordLoginEvent(User $user, LoginChannel $channel, \DateTimeInterface $at): void
    {
        try {
            UserLoginEvent::query()->create([
                'user_id' => $user->id,
                'channel' => $channel->value,
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
                'created_at' => $at,
            ]);
        } catch (Throwable $e) {
            Log::warning('Could not record the sign-in event.', [
                'user_id' => $user->id,
                'channel' => $channel->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
