<?php

use App\Features\System\Http\Controllers\Web\LandingPageController;
use App\Features\System\Http\Controllers\Web\WellKnownController;
use App\Features\Admin\Http\Controllers\Web\AdminConsoleController;
use App\Features\Admin\Http\Controllers\Web\UserAdminController;
use App\Features\Admin\Http\Controllers\Web\GatewayPaymentAdminController;
use App\Features\Admin\Http\Controllers\Web\AdminSupportTicketController;
use App\Features\Admin\Http\Controllers\Web\NotificationTemplateAdminController;
use App\Features\Admin\Http\Controllers\Web\TelegramAdminController;
use App\Features\Admin\Http\Controllers\Web\SystemSettingsController;
use App\Features\Admin\Http\Controllers\Web\EnvironmentSettingsController;
use App\Features\Support\Http\Controllers\Web\ContactController;
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

// App-link verification files (ERR-28). Both 404 until configured.
Route::get('/.well-known/assetlinks.json', [WellKnownController::class, 'assetLinks'])->name('well-known.assetlinks');
Route::get('/.well-known/apple-app-site-association', [WellKnownController::class, 'appleAppSiteAssociation'])->name('well-known.apple-app-site-association');

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

Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:guest-support')->name('contact.store');

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
    Route::post('/login', [AdminConsoleController::class, 'login'])->middleware('throttle:admin-auth')->name('login.submit');

    Route::middleware(EnsureAdminWebAccount::class)->group(function (): void {
        Route::post('/logout', [AdminConsoleController::class, 'logout'])->name('logout');
        Route::get('/', [AdminConsoleController::class, 'dashboard'])->name('dashboard');
        Route::get('/gateway-payments', [GatewayPaymentAdminController::class, 'index'])->name('gateway-payments.index');
        Route::get('/gateway-payments/{intent}', [GatewayPaymentAdminController::class, 'show'])->whereNumber('intent')->name('gateway-payments.show');
        Route::post('/gateway-payments/{intent}/revalidate', [GatewayPaymentAdminController::class, 'revalidate'])->whereNumber('intent')->middleware('throttle:30,1')->name('gateway-payments.revalidate');
        Route::post('/gateway-payments/{intent}/decision', [GatewayPaymentAdminController::class, 'decide'])->whereNumber('intent')->middleware('throttle:30,1')->name('gateway-payments.decision');
        Route::get('/gateway-balances', [GatewayPaymentAdminController::class, 'balances'])->middleware('throttle:20,1')->name('gateway-balances.index');
        // Friendly settings console: one tab per system_settings group.
        Route::get('/settings', [SystemSettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [SystemSettingsController::class, 'update'])->middleware('throttle:60,1')->name('settings.update');
        // Provider credentials written straight to the deployment's .env file.
        Route::patch('/settings/api-keys', [EnvironmentSettingsController::class, 'update'])->middleware('throttle:30,1')->name('settings.environment.update');
        Route::get('/notifications', [NotificationTemplateAdminController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/settings', [NotificationTemplateAdminController::class, 'updateSettings'])->name('notifications.settings');
        Route::post('/notifications/test', [NotificationTemplateAdminController::class, 'sendTest'])->middleware('throttle:3,1')->name('notifications.test');
        Route::get('/notifications/templates/{template}/edit', [NotificationTemplateAdminController::class, 'edit'])->whereNumber('template')->name('notifications.templates.edit');
        Route::patch('/notifications/templates/{template}', [NotificationTemplateAdminController::class, 'update'])->whereNumber('template')->name('notifications.templates.update');
        // Telegram announcements, bot configuration and delivery history.
        Route::get('/telegram', [TelegramAdminController::class, 'index'])->name('telegram.index');
        Route::patch('/telegram/settings', [TelegramAdminController::class, 'updateSettings'])->middleware('throttle:30,1')->name('telegram.settings');
        Route::post('/telegram/test-connection', [TelegramAdminController::class, 'testConnection'])->middleware('throttle:6,1')->name('telegram.test-connection');
        Route::post('/telegram/test-message', [TelegramAdminController::class, 'sendTest'])->middleware('throttle:6,1')->name('telegram.test-message');
        Route::post('/telegram/messages', [TelegramAdminController::class, 'send'])->middleware('throttle:20,1')->name('telegram.send');
        Route::post('/telegram/messages/{message}/resend', [TelegramAdminController::class, 'resend'])->whereNumber('message')->middleware('throttle:20,1')->name('telegram.resend');
        Route::get('/telegram/messages/{message}/image', [TelegramAdminController::class, 'image'])->whereNumber('message')->name('telegram.image');
        Route::get('/support-tickets', [AdminSupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support-tickets/{ticket}', [AdminSupportTicketController::class, 'show'])->whereNumber('ticket')->name('support-tickets.show');
        Route::post('/support-tickets/{ticket}/messages', [AdminSupportTicketController::class, 'reply'])->whereNumber('ticket')->name('support-tickets.reply');
        Route::patch('/support-tickets/{ticket}', [AdminSupportTicketController::class, 'update'])->whereNumber('ticket')->name('support-tickets.update');
        Route::get('/review-templates/{template}/versions', [AdminConsoleController::class, 'templateVersions'])->whereNumber('template')->name('review-templates.versions');
        Route::post('/review-templates/{template}/versions', [AdminConsoleController::class, 'createTemplateVersion'])->whereNumber('template')->name('review-templates.versions.store');
        Route::post('/review-templates/{template}/versions/{version}/publish', [AdminConsoleController::class, 'publishTemplateVersion'])->whereNumber('template')->whereNumber('version')->name('review-templates.versions.publish');
        Route::get('/modules/{module}/{record}/proof', [AdminConsoleController::class, 'moderationProof'])->name('modules.proof');
        Route::get('/users', [UserAdminController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserAdminController::class, 'show'])->whereNumber('user')->name('users.show');
        Route::get('/users/{user}/wallet', [UserAdminController::class, 'wallet'])->whereNumber('user')->name('users.wallet');
        Route::get('/users/{user}/tasks', [UserAdminController::class, 'tasks'])->whereNumber('user')->name('users.tasks');
        Route::get('/users/{user}/withdrawals', [UserAdminController::class, 'withdrawals'])->whereNumber('user')->name('users.withdrawals');
        Route::get('/users/{user}/referrals', [UserAdminController::class, 'referrals'])->whereNumber('user')->name('users.referrals');
        Route::get('/users/{user}/notifications', [UserAdminController::class, 'notifications'])->whereNumber('user')->name('users.notifications');
        Route::get('/users/{user}/activity', [UserAdminController::class, 'activity'])->whereNumber('user')->name('users.activity');
        Route::patch('/users/{user}/profile', [UserAdminController::class, 'updateProfile'])->whereNumber('user')->name('users.profile.update');
        Route::post('/users/{user}/actions/{action}', [UserAdminController::class, 'action'])->whereNumber('user')->name('users.action');
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
