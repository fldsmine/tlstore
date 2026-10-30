<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActivated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->package_id) {
            return redirect()
                ->route('dashboard', ['activate' => 1])
                ->with('status', 'Activate a package to use this feature.');
        }

        return $next($request);
    }
}
