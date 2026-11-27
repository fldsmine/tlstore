<?php

use App\Features\System\Http\Controllers\Web\LandingPageController;
use App\Features\Admin\Http\Controllers\Web\AdminConsoleController;
use App\Features\Admin\Http\Controllers\Web\AdminSupportTicketController;
use App\Features\Admin\Http\Controllers\Web\NotificationTemplateAdminController;
use App\Http\Middleware\EnsureAdminWebAccount;
use App\Services\TelegramBotUsername;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Keep common app entry links useful when the mobile app is not installed.
$webAppUrl = static fn (): string => rtrim(config('app.web_url'), '/');
$flutterWebEntry = static function () {
    $entryPoint = public_path('web/index.html');
    abort_unless(is_file($entryPoint), 404, 'Flutter web build is missing.');

    return response()->file($entryPoint, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
};

Route::get('/', function (Request $request, LandingPageController $landing) use ($webAppUrl, $flutterWebEntry) {
    $webHost = parse_url($webAppUrl(), PHP_URL_HOST);
	
    return $request->getHost() === $webHost
        ? $flutterWebEntry()
        : $landing();
})->name('frontend');

foreach (['login', 'register'] as $appPath) {
    Route::get('/'.$appPath, function (Request $request) use ($appPath, $webAppUrl, $flutterWebEntry) {

		$webHost = parse_url($webAppUrl(), PHP_URL_HOST);
		
        if ($request->getHost() === $webHost) {
            return $flutterWebEntry();
        }

        $query = $request->getQueryString();
        $destination = $webAppUrl().'/'.$appPath;

        return redirect()->away($destination.($query ? '?'.$query : ''));
    })->name('frontend.'.$appPath);
}

Route::get('/ref/{code}', function (string $code) use ($webAppUrl) {
    abort_unless(preg_match('/^[A-Za-z0-9_-]{1,64}$/', $code) === 1, 404);

    return response()->view('referral-redirect', [
        'referralCode' => strtoupper($code),
        'appUrl' => 'tlstore://register?'.http_build_query(['ref' => strtoupper($code)]),
        'webUrl' => $webAppUrl().'/register?'.http_build_query(['ref' => strtoupper($code)]),
    ], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
})->name('referral.redirect');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminConsoleController::class, 'loginForm'])->name('login');
    Route::post('/login', [AdminConsoleController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');

    Route::middleware(EnsureAdminWebAccount::class)->group(function (): void {
        Route::post('/logout', [AdminConsoleController::class, 'logout'])->name('logout');
        Route::get('/', [AdminConsoleController::class, 'dashboard'])->name('dashboard');
        Route::get('/notifications', [NotificationTemplateAdminController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/settings', [NotificationTemplateAdminController::class, 'updateSettings'])->name('notifications.settings');
        Route::post('/notifications/test', [NotificationTemplateAdminController::class, 'sendTest'])->middleware('throttle:3,1')->name('notifications.test');
        Route::get('/notifications/templates/{template}/edit', [NotificationTemplateAdminController::class, 'edit'])->whereNumber('template')->name('notifications.templates.edit');
        Route::patch('/notifications/templates/{template}', [NotificationTemplateAdminController::class, 'update'])->whereNumber('template')->name('notifications.templates.update');
        Route::get('/support-tickets', [AdminSupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support-tickets/{ticket}', [AdminSupportTicketController::class, 'show'])->whereNumber('ticket')->name('support-tickets.show');
        Route::post('/support-tickets/{ticket}/messages', [AdminSupportTicketController::class, 'reply'])->whereNumber('ticket')->name('support-tickets.reply');
        Route::patch('/support-tickets/{ticket}', [AdminSupportTicketController::class, 'update'])->whereNumber('ticket')->name('support-tickets.update');
        Route::get('/review-templates/{template}/versions', [AdminConsoleController::class, 'templateVersions'])->whereNumber('template')->name('review-templates.versions');
        Route::post('/review-templates/{template}/versions', [AdminConsoleController::class, 'createTemplateVersion'])->whereNumber('template')->name('review-templates.versions.store');
        Route::post('/review-templates/{template}/versions/{version}/publish', [AdminConsoleController::class, 'publishTemplateVersion'])->whereNumber('template')->whereNumber('version')->name('review-templates.versions.publish');
        Route::get('/modules/{module}/{record}/proof', [AdminConsoleController::class, 'moderationProof'])->name('modules.proof');
        Route::get('/users/{user}', [AdminConsoleController::class, 'userDetail'])->whereNumber('user')->name('users.show');
        Route::post('/users/{user}/actions/{action}', [AdminConsoleController::class, 'userAction'])->whereNumber('user')->name('users.action');
        Route::get('/modules/{module}', [AdminConsoleController::class, 'index'])->name('modules.index');
        Route::get('/modules/{module}/create', [AdminConsoleController::class, 'create'])->name('modules.create');
        Route::post('/modules/{module}', [AdminConsoleController::class, 'store'])->name('modules.store');
        Route::get('/modules/{module}/{record}/edit', [AdminConsoleController::class, 'edit'])->name('modules.edit');
        Route::patch('/modules/{module}/{record}', [AdminConsoleController::class, 'update'])->name('modules.update');
        Route::delete('/modules/{module}/{record}', [AdminConsoleController::class, 'destroy'])->name('modules.destroy');
        Route::post('/modules/{module}/{record}/actions/{action}', [AdminConsoleController::class, 'moduleAction'])->name('modules.action');
    });
});

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
Route::get('/{path}', function (Request $request) use ($webAppUrl, $flutterWebEntry) {
    $webHost = parse_url($webAppUrl(), PHP_URL_HOST);

    abort_unless($request->getHost() === $webHost, 404);

    return $flutterWebEntry();
})->where('path', '^(?!api(?:/|$)|payments/return$|auth/telegram-widget$|firebase-messaging-sw\.js$|up$|storage(?:/|$)).*')
    ->name('webapp.spa');
