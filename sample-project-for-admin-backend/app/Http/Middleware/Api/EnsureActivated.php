<?php

namespace App\Http\Middleware\Api;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API twin of the web EnsureActivated middleware: some features only exist
 * for users who have activated a package. Web redirects; the API answers 403.
 */
class EnsureActivated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->package_id) {
            return ApiResponse::error('Activate a package to use this feature.', 403);
        }

        return $next($request);
    }
}
