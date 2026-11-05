<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\TelegramBotUsername;

// Hosted gateways return to this thin bridge, which reopens the mobile app.
Route::get('/payments/return', function (Request $request) {
    $intentId = filter_var($request->query('intent_id'), FILTER_VALIDATE_INT);
    abort_unless($intentId !== false && $intentId > 0, 404);

    return redirect()->away('trustlens://payment-return?intent_id='.$intentId);
})->name('payments.return');

Route::get('/auth/telegram-widget', function (Request $request, TelegramBotUsername $telegramBot) {
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
        'webCallbackUrl' => route('frontend'),
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

// Serve the Flutter web build for the site root and client-side routes. Its
// static assets remain under /web/, where Apache can serve them directly.
$flutterWebEntry = static function () {
    $entryPoint = public_path('web/index.html');
    abort_unless(is_file($entryPoint), 404, 'Flutter web build is missing.');

    return response()->file($entryPoint, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
};

Route::get('/', $flutterWebEntry)->name('frontend');
Route::get('/{path}', $flutterWebEntry)
    ->where('path', '^(?!api(?:/|$)|payments/return$|auth/telegram-widget$|up$|storage(?:/|$)).*')
    ->name('frontend.spa');
