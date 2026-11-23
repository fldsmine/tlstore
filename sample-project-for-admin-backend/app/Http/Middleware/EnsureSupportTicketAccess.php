<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** Allows normal user tokens and the restricted token issued to suspended users. */
class EnsureSupportTicketAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken
            && ! $token->can('*')
            && ! $token->can('support-tickets')) {
            return ApiResponse::error('You are not allowed to access support tickets.', 403);
        }

        return $next($request);
    }
}
