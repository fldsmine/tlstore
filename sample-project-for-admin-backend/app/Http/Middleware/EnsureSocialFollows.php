<?php

namespace App\Http\Middleware;

use App\Services\SocialFollowRequirement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSocialFollows
{
    public function __construct(private readonly SocialFollowRequirement $requirements)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->requirements->hasPending($user)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
