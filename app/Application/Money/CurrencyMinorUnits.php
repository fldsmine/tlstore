<?php

namespace App\Application\Money;

final class CurrencyMinorUnits
{
    public static function precision(string $currency): int
    {
        return (int) config('trustlens.currency_minor_units.'.strtoupper($currency), 2);
    }

    public static function factor(string $currency): int
    {
        return 10 ** self::precision($currency);
    }

    public static function toMinor(float|int|string $amount, string $currency): int
    {
        return (int) round((float) $amount * self::factor($currency));
    }

    public static function fromMinor(int $amountMinor, string $currency): float
    {
        return $amountMinor / self::factor($currency);
    }
}
