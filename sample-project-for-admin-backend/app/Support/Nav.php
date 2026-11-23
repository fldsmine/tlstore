<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

class Nav
{
    public static function parentUrl(string $fallbackRoute = 'dashboard'): string
    {
        $segments = explode('/', trim(self::currentPath(), '/'));

        while (count($segments) > 1) {
            array_pop($segments);
            $candidate = '/'.implode('/', $segments);

            if (self::resolves($candidate)) {
                return url($candidate);
            }
        }

        return route($fallbackRoute);
    }

    private static function currentPath(): string
    {
        $path = request()->path();

        if (! str_starts_with($path, 'livewire/')) {
            return $path;
        }

        $referer = (string) request()->headers->get('referer', '');

        return trim((string) parse_url($referer, PHP_URL_PATH), '/');
    }

    private static function resolves(string $path): bool
    {
        try {
            return Route::getRoutes()->match(Request::create($path, 'GET')) !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
