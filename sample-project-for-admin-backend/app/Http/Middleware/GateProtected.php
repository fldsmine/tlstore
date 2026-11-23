<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Access gate for the authenticated Livewire sections: requests that do not
 * carry the configured app_gate cookie are dropped with an empty 403 — the
 * server returns nothing. Public entry points mint the cookie (GateCookie),
 * so every legitimate client has it before its first gated request.
 *
 * When APP_GATE_SECRET is not configured the gate is inactive (dev/CI).
 */
class GateProtected
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('services.gate.secret', '');

        if ($secret !== '' && hash_equals($secret, (string) $request->cookie((string) config('services.gate.cookie', 'app_gate'))) === false) {
            return response('', 403);
        }

        return $next($request);
    }
}
