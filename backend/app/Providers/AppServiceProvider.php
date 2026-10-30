<?php

namespace App\Providers;

use App\Contracts\BankVerifier;
use App\Services\CurrencyService;
use App\Services\Payments\Gateways\FlutterwaveGateway;
use App\Services\Payments\Gateways\PaystackGateway;
use App\Services\Payments\MockVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\BackupZipWasCreated;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        $this->app->singleton(CurrencyService::class);

        $this->app->bind(BankVerifier::class, function (): BankVerifier {
            $provider = config('settings.payment.bank_verification_provider', 'paystack');
            $keyPath = $provider === 'flutterwave'
                ? 'services.flutterwave.secret_key'
                : 'services.paystack.secret_key';

            if (blank(config($keyPath))) {
                return new MockVerifier;
            }

            return $provider === 'flutterwave' ? app(FlutterwaveGateway::class) : app(PaystackGateway::class);
        });
    }

    public function boot(): void
    {

        Model::unguard();
        Model::shouldBeStrict();

        Password::defaults(fn () => Password::min(8));

        $this->configureApiRateLimiting();
        $this->configureMailingRateLimiting();

        Event::listen(BackupZipWasCreated::class, fn () => $this->raiseBackupMemory());

        Event::listen(BackupWasSuccessful::class, fn () => Cache::forever('backup:last_success_at', now()->toIso8601String()));
    }

    /**
     * API rate limiters. Money-moving and credential-checking endpoints get the
     * tightest caps; the catch-all `api` limiter keeps any single client honest.
     */
    private function configureApiRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limit', 120))
                ->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request): Limit {
            $identity = strtolower(trim((string) $request->input('email', $request->input('login', ''))));

            return Limit::perMinute(10)->by($identity.'|'.$request->ip());
        });

        RateLimiter::for('api-register', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api-otp', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by(($request->user()?->getAuthIdentifier() ?: '').'|'.$request->ip());
        });

        RateLimiter::for('api-sensitive', function (Request $request): Limit {
            return Limit::perMinute(6)
                ->by(($request->user()?->getAuthIdentifier() ?: '').'|'.$request->ip());
        });
    }

    private function configureMailingRateLimiting(): void
    {
        RateLimiter::for('mailing-campaign-emails', fn (object $job): Limit => Limit::perMinute(max(1, (int) config('mailing.rate_per_minute', 60)))
            ->by('mailing-campaigns'));
    }

    private function raiseBackupMemory(): void
    {
        $limit = (string) ini_get('memory_limit');
        if ($limit === '-1') {
            return;
        }

        $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        if ($bytes < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
    }
}
