<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\LedgerEntry;
use App\Observers\LedgerEntryObserver;
use App\Observers\NotificationObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        RateLimiter::for('withdrawal-request', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(8)->by($userId ? 'withdrawal-user:'.$userId : $request->ip());
        });
        Notification::observe(NotificationObserver::class);
        LedgerEntry::observe(LedgerEntryObserver::class);
    }
}
