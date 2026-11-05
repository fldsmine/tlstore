<?php

namespace App\Application\Support;

use Illuminate\Support\Facades\URL;

/**
 * URL helpers for files on the public disk (covers, avatars, ads, logos, polls).
 *
 * Storage paths in the database are disk-relative (e.g. "covers/2026/09/x.png").
 * This helper turns them into absolute URLs, using the public disk's configured
 * URL when set (APP_URL/storage or a CDN) and falling back to the current
 * request host otherwise. Legacy rows that already store absolute URLs pass
 * through unchanged.
 */
class Media
{
    public static function url(?string $path, ?string $disk = 'public'): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path; // already absolute (legacy rows)
        }

        // Old data can contain either "images/file.jpg" or
        // "/storage/images/file.jpg". Both map to the public storage URL.
        $path = preg_replace('#^(?:\./)?/?storage/#i', '', trim($path));
        $path = ltrim($path, '/');

        $base = $disk ? config("filesystems.disks.{$disk}.url") : null;
        $base = $base ?: config('filesystems.disks.public.url');

        if (! is_string($base) || trim($base) === '') {
            $base = rtrim(URL::to('/'), '/').'/storage';
        }

        return rtrim($base, '/').'/'.$path;
    }
}
