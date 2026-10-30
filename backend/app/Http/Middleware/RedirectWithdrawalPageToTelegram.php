<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RedirectWithdrawalPageToTelegram
{
    public const DESTINATION = 'https://t.me/+1idnQ62MwB84ZDQ0';

    private const CACHE_KEY_PREFIX = 'withdrawal-telegram-redirected:user:';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $cacheKey = self::CACHE_KEY_PREFIX.$user->getAuthIdentifier();
        $cacheMinutes = max(1, (int) config('withdrawal_redirect.cache_minutes', 1440));

        // Cache::add is atomic on the configured cache store: only the first
        // request for this user in the cache window receives the redirect.
        if (! Cache::add($cacheKey, true, now()->addMinutes($cacheMinutes))) {
            return $next($request);
        }

        // Telegram's SDK can open the destination natively from its Mini App.
        // Other clients receive a normal external redirect (Flutter handles this
        // exact destination with its external-link opener).
        if ((bool) $request->session()->get('mini_app')) {
            return response()->view('redirects.withdrawal-telegram', [
                'destination' => self::DESTINATION,
            ]);
        }

        return redirect()->away(self::DESTINATION);
    }
}
