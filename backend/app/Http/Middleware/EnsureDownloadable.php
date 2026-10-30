<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorises a file download.
 *
 * Two ways in, because the same link is followed by three different clients (see DownloadLinks):
 *
 *   - a valid temporary signature — what the dashboard puts in the href, and the only thing a
 *     cookie-less client (Telegram's downloadFile) can present; or
 *   - an authenticated web session — a browser or the Android app, which keep their cookies.
 *
 * The download routes sit outside the auth/onboarding/access-gate middleware on purpose: those
 * would redirect a cookie-less request to the login page instead of returning the file.
 */
class EnsureDownloadable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() || $request->hasValidSignature()) {
            return $next($request);
        }

        abort(403, 'This download link is invalid or has expired. Please open the page again and retry.');
    }
}
