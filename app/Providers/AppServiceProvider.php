<?php

namespace App\Providers;

use App\Application\Settings\EnvironmentFile;
use App\Models\LedgerEntry;
use App\Models\Notification;
use App\Observers\LedgerEntryObserver;
use App\Observers\NotificationObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The console edits the deployment's .env file; tests bind a temp file.
        $this->app->bind(EnvironmentFile::class, static fn (): EnvironmentFile => new EnvironmentFile(base_path('.env')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $general = gs();
        config(['settings' => $general]);

        RateLimiter::for('withdrawal-request', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(8)->by($userId ? 'withdrawal-user:'.$userId : $request->ip());
        });

        // The public contact form is unauthenticated: cap submissions per source
        // address *and* per email address so one script cannot flood the inbox.
        RateLimiter::for('guest-support', function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(4)->by('guest-support-ip:'.$request->ip()),
                Limit::perHour(6)->by('guest-support-email:'.sha1($email)),
            ];
        });

        // Administrator sign-in is a high-value target: throttle per account
        // *and* per source address, so neither password spraying across many
        // accounts nor repeated attempts against one account get more than a
        // handful of tries per minute.
        RateLimiter::for('admin-auth', function (Request $request): array {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('admin-auth:'.sha1($email.'|'.$request->ip())),
                Limit::perMinute(20)->by('admin-auth-ip:'.$request->ip()),
            ];
        });
        Notification::observe(NotificationObserver::class);
        LedgerEntry::observe(LedgerEntryObserver::class);
    }
}
