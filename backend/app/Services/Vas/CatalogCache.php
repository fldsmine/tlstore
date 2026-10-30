<?php

namespace App\Services\Vas;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Catalogue caching (billers + items). An empty result is never cached so a transient API failure
 * can't poison the catalogue for the whole TTL; refreshing() bypasses the read so the warm command
 * refetches, and an empty refetch leaves the last good cache in place.
 */
class CatalogCache
{
    private static bool $refreshing = false;

    /** Run $callback with cached reads bypassed so remember() refetches — used by the warm command. */
    public static function refreshing(Closure $callback): void
    {
        self::$refreshing = true;

        try {
            $callback();
        } finally {
            self::$refreshing = false;
        }
    }

    /**
     * @param  Closure(): array<mixed>  $fetch
     * @return array<mixed>
     */
    public static function remember(string $key, int $hours, Closure $fetch): array
    {
        if (! self::$refreshing) {
            $cached = Cache::get($key);

            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        }

        $fresh = $fetch();

        if ($fresh !== []) {
            Cache::put($key, $fresh, now()->addHours(max(1, $hours)));
        }

        return $fresh;
    }
}
