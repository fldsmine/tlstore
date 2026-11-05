<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->status !== UserStatus::Active || $user->is_banned) {
            return response()->json(['message' => 'An active user account is required.'], 403);
        }

        return $next($request);
    }
}
