<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns a raw User-Agent string into something an admin can read at a glance.
 *
 * The backoffice sign-in report lists one row per sign-in, and a wall of
 * "Mozilla/5.0 (Linux; Android 14; SM-A155F) AppleWebKit/537.36…" is not
 * scannable. This is deliberately a small heuristic — enough to answer "phone or
 * laptop, and which browser" — not a full UA database, and it never throws on
 * whatever a client decides to send.
 */
class DeviceInfo
{
    /** "Android · Chrome" style summary, or "Unknown device" for an empty/odd UA. */
    public static function describe(?string $userAgent): string
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return 'Unknown device';
        }

        $platform = self::platform($ua);
        $browser = self::browser($ua);

        return match (true) {
            $platform !== null && $browser !== null => $platform.' · '.$browser,
            $platform !== null => $platform,
            $browser !== null => $browser,
            default => Str::limit($ua, 60, '…'),
        };
    }

    /** The OS/app shell behind the request, or null when it cannot be told. */
    public static function platform(?string $userAgent): ?string
    {
        $ua = (string) $userAgent;

        return match (true) {
            $ua === '' => null,
            // The Telegram Mini App's WebView, and Telegram's own in-app browser.
            Str::contains($ua, 'Telegram') => Str::contains($ua, ['Android', 'Linux']) ? 'Telegram · Android' : 'Telegram WebView',
            Str::contains($ua, 'Android') => 'Android',
            Str::contains($ua, ['iPhone', 'iPod']) => 'iPhone',
            Str::contains($ua, 'iPad') => 'iPad',
            Str::contains($ua, ['Macintosh', 'Mac OS X']) => 'macOS',
            Str::contains($ua, 'Windows') => 'Windows',
            Str::contains($ua, ['CrOS']) => 'ChromeOS',
            Str::contains($ua, 'Linux') => 'Linux',
            default => null,
        };
    }

    /** The browser/app product, or null when it cannot be told. */
    public static function browser(?string $userAgent): ?string
    {
        $ua = (string) $userAgent;

        // Order matters: every Chromium browser claims both Chrome and Safari.
        return match (true) {
            $ua === '' => null,
            Str::contains($ua, 'Edg') => 'Edge',
            Str::contains($ua, 'OPR/') || Str::contains($ua, 'Opera') => 'Opera',
            Str::contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            Str::contains($ua, 'Firefox') => 'Firefox',
            Str::contains($ua, 'CriOS') => 'Chrome',
            Str::contains($ua, 'FxiOS') => 'Firefox',
            Str::contains($ua, 'Chrome') => 'Chrome',
            Str::contains($ua, 'Safari') => 'Safari',
            Str::contains($ua, 'curl') => 'curl',
            default => null,
        };
    }

    /** True when the request came from a phone or tablet (used to badge mobile sign-ins). */
    public static function isMobile(?string $userAgent): bool
    {
        $ua = (string) $userAgent;

        return Str::contains($ua, ['Android', 'iPhone', 'iPad', 'iPod', 'Mobile']);
    }
}
