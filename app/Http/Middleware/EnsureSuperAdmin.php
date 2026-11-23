<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super-admin gate for the backoffice. Applied on top of the `admin.session` group.
 * Livewire components additionally re-check isSuperAdmin() inside their actions.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('admin')?->isSuperAdmin() ?? false, 403, 'This area is restricted to super administrators.');

        return $next($request);
    }
}
