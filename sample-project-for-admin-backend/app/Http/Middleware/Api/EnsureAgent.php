<?php

namespace App\Http\Middleware\Api;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gates the vendor/agent API surface — only accounts flagged is_agent may pass. */
class EnsureAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_agent) {
            return ApiResponse::error('This action is only available to vendors.', 403);
        }

        return $next($request);
    }
}
