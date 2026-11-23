<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureAdminAccount;
use App\Http\Middleware\EnsureAdminSession;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserAccount;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Admin backoffice (Livewire, server-rendered). Registered as an
            // ADDITIVE then-block: /, /web/*, /api/* and /payments/* are untouched.
            Route::middleware('web')
                ->prefix(config('backoffice.path'))
                ->name('backoffice.')
                ->group(base_path('routes/backoffice.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.account' => EnsureAdminAccount::class,
            'user.account' => EnsureUserAccount::class,
            'user.two-factor' => EnsureTwoFactorAuthenticated::class,
            'admin.session' => EnsureAdminSession::class,
            'admin.super' => EnsureSuperAdmin::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('backoffice.*')
            ? route('backoffice.login')
            : route('frontend'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('backoffice.*')
            ? route('backoffice.dashboard')
            : route('frontend'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
