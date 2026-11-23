<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-rendered backoffice gate (web/session). Mirrors EnsureAdminAccount for the
 * API, but redirects web guests to the backoffice login instead of answering JSON.
 *
 * Note: Livewire update endpoints (/livewire/update) do NOT pass through this
 * middleware — every Livewire component that performs a sensitive action must
 * re-check authorization server-side (see Livewire\Backoffice\Concerns).
 */
class EnsureAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if (! $admin) {
            return redirect()->route('backoffice.login');
        }

        if ($admin->status !== 'active') {
            auth()->guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('backoffice.login')
                ->withErrors(['email' => 'This administrator account is not active.']);
        }

        return $next($request);
    }
}
