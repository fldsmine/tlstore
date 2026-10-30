<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Mints the access-gate cookie on every non-gated response (mini-app page,
 * public auth pages, handoff endpoint) so browsers and the Telegram WebView
 * carry it automatically on all subsequent requests — including the first
 * load of a gated Livewire page.
 */
class GateCookie
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $secret = (string) config('services.gate.secret', '');
        $name = (string) config('services.gate.cookie', 'app_gate');

        if ($secret === '' || (string) $request->cookie($name) === $secret) {
            return $response;
        }

        if (method_exists($response, 'withCookie')) {
            $response->withCookie(cookie(
                $name,
                $secret,
                60 * 24 * 30,                          // 30 days
                '/',
                config('services.gate.domain') ?: null, // e.g. .easyportaltopupxyz.com
                $request->secure(),                    // secure on HTTPS (same rule as the session cookie)
                true,                                  // httpOnly — the browser auto-sends it, JS never needs it
                false,
                'lax',
            ));
        }

        return $response;
    }
}
