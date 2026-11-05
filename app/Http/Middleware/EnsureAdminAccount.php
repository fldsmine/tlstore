<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ($request->user() instanceof Admin) || $request->user()->status !== 'active') {
            return response()->json(['message' => 'An active administrator account is required.'], 403);
        }

        return $next($request);
    }
}
