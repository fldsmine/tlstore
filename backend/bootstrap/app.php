<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix(config('backoffice.path'))
                ->name('backoffice.')
                ->group(base_path('routes/backoffice.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            CheckMaintenanceMode::class,
            \App\Http\Middleware\GateCookie::class,
        ]);

        $middleware->alias([
            'api.activated' => App\Http\Middleware\Api\EnsureActivated::class,
            'api.agent' => App\Http\Middleware\Api\EnsureAgent::class,
            'gate' => App\Http\Middleware\GateProtected::class,
        ]);

        // The gate cookie travels as plain text (it is not a session secret;
        // encrypting it would break cross-instance verification).
        $middleware->encryptCookies(except: [
            'app_gate',
        ]);

        // The access gate must drop cookie-less requests BEFORE the auth
        // middleware can redirect guests to the login page.
        $middleware->prependToPriorityList(
            Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            App\Http\Middleware\GateProtected::class,
        );

        // Inbound provider webhooks carry no CSRF token.
        $middleware->validateCsrfTokens(except: [
            'webhooks/vtpass',
            'webhooks/payments/*',
            'webhooks/telegram',
            'api/v1/web-session',
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('backoffice.*')
            ? route('backoffice.login')
            : route('auth.login'));

        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('backoffice.*')
            ? route('backoffice.dashboard')
            : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // The API always answers with the standard JSON envelope — never HTML.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($e->getMessage() ?: 'Unauthenticated.', 401);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($e->getMessage(), $e->status, $e->errors());
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
                return ApiResponse::error('Not found.', 404);
            }

            if ($e instanceof AccessDeniedHttpException) {
                return ApiResponse::error($e->getMessage() ?: 'Forbidden.', 403);
            }

            if ($e instanceof ThrottleRequestsException) {
                return ApiResponse::error('Too many requests. Please slow down.', 429)
                    ->withHeaders($e->getHeaders());
            }

            return null;
        });
    })->create();
