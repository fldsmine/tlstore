<?php

namespace App\Http\Middleware;

use App\Support\Onboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($route = Onboarding::nextRoute($user)) !== null) {
            return redirect()->route($route);
        }

        return $next($request);
    }
}
