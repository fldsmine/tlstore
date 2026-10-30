<?php

namespace App\Support;

use App\Models\User;
use App\Services\CurrencyService;

class DisplayCurrency
{
    /**
     * The user whose currency preference applies: the given user, otherwise the
     * authenticated one — but only when it is a User. Admins (backoffice guard)
     * have no display currency, so they fall through to the USD default.
     */
    protected static function resolve(?User $user): ?User
    {
        if ($user instanceof User) {
            return $user;
        }

        $authenticated = auth()->user();

        return $authenticated instanceof User ? $authenticated : null;
    }

    public static function code(?User $user = null): string
    {
        $user = self::resolve($user);

        if ($user === null) {
            return 'USD';
        }

        $preferred = strtoupper((string) $user->display_currency);

        return $preferred !== '' && $preferred === strtoupper(self::localCode($user)) && self::hasLocal($user)
            ? $preferred
            : 'USD';
    }

    public static function isLocal(?User $user = null): bool
    {
        return self::code($user) !== 'USD';
    }

    public static function localCode(?User $user = null): string
    {
        $user = self::resolve($user);

        return $user === null ? 'USD' : app(CurrencyService::class)->code($user->country);
    }

    public static function convert(float $usd, ?User $user = null, int $decimals = 2): float
    {
        $user = self::resolve($user);

        return self::isLocal($user)
            ? app(CurrencyService::class)->toLocal($usd, $user->country, $decimals)
            : round($usd, $decimals);
    }

    public static function symbol(?User $user = null): string
    {
        $user = self::resolve($user);

        return self::isLocal($user) ? app(CurrencyService::class)->symbol($user->country) : '$';
    }

    public static function autoDecimals(float $converted): int
    {
        if ($converted == 0.0) {
            return 2;
        }

        // Fewest places (2–8) that render the value non-zero and lose no significance —
        // so a sub-cent earning like $0.00007 shows exactly instead of rounding to 0.00.
        for ($places = 2; $places < 6; $places++) {
            if (round(abs($converted), $places) > 0
                && abs(round($converted, $places) - round($converted, 6)) < 1e-9) {
                return $places;
            }
        }

        return 6;
    }

    public static function format(float $usd, int|string $decimals = 2, ?User $user = null): string
    {
        $user = self::resolve($user);

        // Local currencies (e.g. NGN) render at their natural 2dp for a clean, consistent look;
        // USD keeps auto precision so sub-cent config values (rates, tiny rewards) stay visible.
        $places = $decimals === 'auto'
            ? (self::isLocal($user) ? 2 : self::autoDecimals(self::convert($usd, $user, 6)))
            : (int) $decimals;

        return self::symbol($user).number_format(self::convert($usd, $user, $places), $places);
    }

    public static function hasLocal(?User $user = null): bool
    {
        $user = self::resolve($user);

        return $user !== null
            && self::localCode($user) !== 'USD'
            && app(CurrencyService::class)->rate($user->country) > 0;
    }
}
