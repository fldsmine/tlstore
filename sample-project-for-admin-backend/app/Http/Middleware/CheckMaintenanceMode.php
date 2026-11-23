<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {

        if ($this->isBackofficeRequest($request)) {
            return $next($request);
        }

        if (config('settings.app.enable_maintenance_mode')) {

            return response()->view('errors.maintenance', [], 503)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return $next($request);
    }

    protected function isBackofficeRequest(Request $request): bool
    {
        $adminPath = trim((string) config('backoffice.path'), '/');

        if ($adminPath === '') {
            return false;
        }

        if ($request->is($adminPath, $adminPath.'/*')) {
            return true;
        }

        $refererPath = (string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);

        return $refererPath === '/'.$adminPath || str_starts_with($refererPath, '/'.$adminPath.'/');
    }
}
