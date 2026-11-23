<?php

namespace App\Http\Middleware;

use App\Enums\AdminRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user('admin')?->role;

        abort_if($role === null, 403);

        if ($role === AdminRole::SUPER) {
            return $next($request);
        }

        foreach ($roles as $allowed) {
            if ($role === AdminRole::tryFrom($allowed)) {
                return $next($request);
            }
        }

        abort(403, 'You are not authorized to access this page.');
    }
}
