<?php

namespace App\Providers;

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
        //
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
