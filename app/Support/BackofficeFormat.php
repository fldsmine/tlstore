<?php

namespace App\Support;

/**
 * Display formatting for backoffice amounts and numbers.
 *
 * Money in this codebase is stored as integer minor units (wallets.balance_minor,
 * ledger_entries.amount_minor, payment_*.*_minor). The `transactions` table is a
 * legacy decimal-major projection and is formatted separately where displayed.
 * Never converts between currencies — each amount is rendered in its own currency.
 */
final class BackofficeFormat
{
    private const SYMBOLS = [
        'NGN' => "\u{20A6}", 'USD' => '$', 'EUR' => "\u{20AC}", 'GBP' => "\u{00A3}",
        'GHS' => "\u{20B5}", 'KES' => 'KSh ', 'ZAR' => 'R ', 'INR' => "\u{20B9}",
        'JPY' => "\u{00A5}", 'XOF' => 'CFA ', 'XAF' => 'FCFA ',
    ];

    public static function minorExponent(string $currency): int
    {
        $map = (array) config('trustlens.currency_minor_units', []);

        return (int) ($map[strtoupper($currency)] ?? 2);
    }

    /** Full precision, grouped, with symbol: ₦12,345.67 */
    public static function moneyMinor(int $minor, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: 'NGN');
        $exponent = self::minorExponent($currency);
        $factor = 10 ** $exponent;
        $amount = $minor / $factor;
        $symbol = self::SYMBOLS[$currency] ?? ($currency.' ');

        return $symbol.number_format($amount, $exponent);
    }

    /**
     * Compact abbreviated form for KPI cards; exact value should be shown as a
     * hint alongside when abbreviated.
     */
    public static function compactMinor(int $minor, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: 'NGN');
        $exponent = self::minorExponent($currency);
        $amount = $minor / (10 ** $exponent);
        $symbol = self::SYMBOLS[$currency] ?? ($currency.' ');

        return $symbol.self::compactNumber($amount);
    }

    public static function compactNumber(float|int $value): string
    {
        $abs = abs((float) $value);
        foreach ([[1e12, 'T'], [1e9, 'B'], [1e6, 'M'], [1e3, 'K']] as [$threshold, $suffix]) {
            if ($abs >= $threshold) {
                return rtrim(rtrim(number_format($value / $threshold, 2), '0'), '.').$suffix;
            }
        }

        return (string) number_format((float) $value, 0);
    }

    /** Exact value formatted for inputs (e.g. 1234.50). */
    public static function minorToInput(int $minor, ?string $currency = null): string
    {
        $exponent = self::minorExponent(strtoupper($currency ?: 'NGN'));

        return number_format($minor / (10 ** $exponent), $exponent, '.', '');
    }

    /** Parse a user-entered major-unit amount into minor units. Returns null when invalid. */
    public static function inputToMinor(string $input, ?string $currency = null): ?int
    {
        $currency = strtoupper($currency ?: 'NGN');
        $exponent = self::minorExponent($currency);
        $input = trim($input);
        if (! preg_match('/^\d{1,15}(\.\d{1,6})?$/', $input)) {
            return null;
        }
        $factor = 10 ** $exponent;
        $minor = (int) round(((float) $input) * $factor);

        return $minor >= 0 ? $minor : null;
    }

    /** Format a legacy decimal-major (transactions.amount) value, preserving sign sense via direction outside. */
    public static function legacyDecimal(string|float|int $amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: 'NGN');
        $symbol = self::SYMBOLS[$currency] ?? ($currency.' ');

        return $symbol.number_format((float) $amount, 2);
    }
}
