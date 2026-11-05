<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->two_factor) {
            $token = $user->currentAccessToken();
            if (! $token instanceof PersonalAccessToken
                || ! in_array('two_factor_verified', $token->abilities, true)) {
                return response()->json([
                    'message' => 'Complete authenticator verification to continue.',
                    'two_factor_required' => true,
                ], 401);
            }
        }

        return $next($request);
    }
}
