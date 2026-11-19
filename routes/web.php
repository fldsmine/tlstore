<?php

use App\Features\System\Http\Controllers\Web\LandingPageController;
use App\Services\TelegramBotUsername;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Keep common app entry links useful when the mobile app is not installed.
$webAppUrl = static fn (): string => rtrim(config('app.web_url', 'https://web.tlstore.org'), '/');
$flutterWebEntry = static function () {
    $entryPoint = public_path('web/index.html');
    abort_unless(is_file($entryPoint), 404, 'Flutter web build is missing.');

    return response()->file($entryPoint, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
};

Route::get('/', function (Request $request, LandingPageController $landing) use ($flutterWebEntry) {
    return $request->getHost() === 'web.tlstore.org'
        ? $flutterWebEntry()
        : $landing();
})->name('frontend');

foreach (['login', 'register'] as $appPath) {
    Route::get('/'.$appPath, function (Request $request) use ($appPath, $webAppUrl, $flutterWebEntry) {
        if ($request->getHost() === 'web.tlstore.org') {
            return $flutterWebEntry();
        }

        $query = $request->getQueryString();
        $destination = $webAppUrl().'/'.$appPath;

        return redirect()->away($destination.($query ? '?'.$query : ''));
    })->name('frontend.'.$appPath);
}

// Hosted gateways return to this thin bridge, which reopens the mobile app.
Route::get('/payments/return', function (Request $request) {
    $intentId = filter_var($request->query('intent_id'), FILTER_VALIDATE_INT);
    abort_unless($intentId !== false && $intentId > 0, 404);

    return redirect()->away('trustlens://payment-return?intent_id='.$intentId);
})->name('payments.return');

Route::get('/auth/telegram-widget', function (Request $request, TelegramBotUsername $telegramBot) use ($webAppUrl) {
    $data = $request->validate([
        'state' => ['required', 'string', 'min:24', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        'web' => ['sometimes', 'boolean'],
        'screen' => ['sometimes', 'in:login,register'],
    ]);
    $username = $telegramBot->get();
    abort_unless($username, 503, 'Telegram sign-in is not configured.');

    return response()->view('auth.telegram-widget', [
        'botUsername' => $username,
        'state' => $data['state'],
        'isWebCallback' => filter_var($data['web'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'webScreen' => $data['screen'] ?? 'login',
        'webCallbackUrl' => $webAppUrl(),
    ]);
})->name('auth.telegram.widget');

Route::get('/firebase-messaging-sw.js', function () {
    $worker = public_path('web/firebase-messaging-sw.js');
    abort_unless(is_file($worker), 404, 'Firebase web messaging worker is missing.');

    return response()->file($worker, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
})->name('firebase.messaging.worker');

// Local preview path while the public root displays the campaign page.
Route::get('/web-app/{path?}', $flutterWebEntry)
    ->where('path', '.*')
    ->name('webapp.local');

// On the web-app subdomain, send Flutter's client-side routes to the app.
// The bundle is stored in public/web, so its generated /web/ asset base works
// from both the subdomain root and nested routes.
Route::get('/{path}', function (Request $request) use ($flutterWebEntry) {
    abort_unless($request->getHost() === 'web.tlstore.org', 404);

    return $flutterWebEntry();
})->where('path', '^(?!api(?:/|$)|payments/return$|auth/telegram-widget$|firebase-messaging-sw\.js$|up$|storage(?:/|$)).*')
    ->name('webapp.spa');
