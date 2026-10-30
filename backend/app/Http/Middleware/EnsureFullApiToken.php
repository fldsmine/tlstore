<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SupportTicketAccess;
use App\Services\Auth\LoginService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** Keeps support-only tokens out of the regular mobile API surface. */
class EnsureFullApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can('*')) {
            return ApiResponse::error('This token can only access support tickets.', 403);
        }

        if ($user instanceof User && app(LoginService::class)->isSuspended($user)) {
            $data = app(SupportTicketAccess::class)->issue($user);

            return ApiResponse::error(
                'This account has been suspended. Please contact support.',
                403,
                data: $data,
            );
        }

        return $next($request);
    }
}
