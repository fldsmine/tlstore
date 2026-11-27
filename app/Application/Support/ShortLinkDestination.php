<?php

namespace App\Application\Support;

/**
 * Destinations for the short links in `public/redirect.php`.
 *
 * The hosted short link used to hardcode `tlstore.org` / `web.tlstore.org`, so
 * staging and white-label deployments sent visitors to production (ERR-26).
 * The public script now bootstraps config and asks this class instead.
 */
final class ShortLinkDestination
{
    /** @var list<string> */
    public const APP_PATHS = ['register', 'login'];

    public static function forReferral(string $code): string
    {
        return self::base('app.url').'/ref/'.rawurlencode($code);
    }

    public static function forAppPath(string $path): string
    {
        $path = in_array($path, self::APP_PATHS, true) ? $path : 'register';

        return self::base('app.web_url').'/'.$path;
    }

    private static function base(string $key): string
    {
        $value = rtrim(trim((string) config($key, '')), '/');
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException("The {$key} URL is not configured.");
        }

        return $value;
    }
}
