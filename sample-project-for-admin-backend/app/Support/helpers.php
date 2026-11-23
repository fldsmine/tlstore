<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\CloudDistributionService;
use App\Services\CloudInboxService;
use App\Services\CurrencyService;
use App\Support\DisplayCurrency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (! function_exists('parse_setting')) {

    function parse_setting(array|Collection $settings): array
    {
        return collect($settings)
            ->map(function ($item, $key): array {
                $value = is_array($item) ? json_encode($item) : htmlentities((string) $item);

                return [
                    'key' => $key,
                    'value' => $value,
                    'id' => strtolower((string) Str::uuid()),
                ];
            })->values()->all();
    }
}

if (! function_exists('cloud_badge_count')) {

    function cloud_badge_count(User $user): int
    {
        if (blank($user->cloud_line_number)) {
            return 0;
        }

        return app(CloudInboxService::class)->unreadCount($user)
            + app(CloudDistributionService::class)->claimableCount($user);
    }
}

if (! function_exists('transform_setting')) {

    function transform_setting(array|Collection $settings): array
    {
        return collect($settings)->mapWithKeys(function ($item): array {
            $value = $item['value'];

            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            return [$item['key'] => $value];
        })->all();
    }
}

if (! function_exists('update_setting')) {

    function update_setting(array $attributes): void
    {
        Setting::upsert(parse_setting($attributes), ['key'], ['value']);

        Cache::forget('settings');
        Artisan::call('config:clear');
    }
}

if (! function_exists('currency')) {

    function currency(): CurrencyService
    {
        return app(CurrencyService::class);
    }
}

if (! function_exists('abbreviate_number')) {

    function abbreviate_number(int|float $value, int $precision = 2): string
    {
        $value = (float) $value;

        if (! is_finite($value)) {
            return '0';
        }

        $abs = abs($value);

        if ($abs < 100000) {
            return number_format($value);
        }

        $units = ['', 'K', 'M', 'B', 'T'];
        $power = min((int) floor(log10($abs) / 3), count($units) - 1);
        $scaled = $value / (10 ** ($power * 3));

        if (abs(round($scaled, $precision)) >= 1000 && $power < count($units) - 1) {
            $power++;
            $scaled = $value / (10 ** ($power * 3));
        }

        $formatted = rtrim(rtrim(number_format($scaled, $precision), '0'), '.');

        return $formatted.$units[$power];
    }
}

if (! function_exists('money')) {

    function money(float|int|string|null $usd, int|string $decimals = 'auto'): string
    {
        return DisplayCurrency::format((float) $usd, $decimals);
    }
}

if (! function_exists('media_url')) {

    function media_url(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        // Absolute or root-relative values (external avatars, legacy /storage paths) pass through;
        // clean storage keys resolve against the configured default disk.
        if (Str::startsWith($value, ['http://', 'https://', '//', '/'])) {
            return $value;
        }

        $url = Storage::url($value);

        // The "public" local disk builds its URL from APP_URL, which rarely matches the origin
        // the app is actually served from off-production (dev-server port, staging host), so the
        // link 404s. Normalise any local-driver media to a root-relative path the browser resolves
        // against the current origin. S3 media lives on another host and must stay absolute.
        $disk = (string) config('filesystems.default');

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path) && $path !== '') {
                $query = parse_url($url, PHP_URL_QUERY);

                return $query ? $path.'?'.$query : $path;
            }
        }

        return $url;
    }
}

if (! function_exists('product_index_url')) {
    /**
     * The product storefront's index, on whichever host this request is being served from.
     *
     * The pages are registered at /products on EVERY host (so the flow works before the dedicated
     * domain's DNS/vhost exists) and at / on the product host itself. Using the current host here
     * means a visitor on product.n-calls.com stays on product.n-calls.com, and one on the main site
     * stays there — neither is bounced to the other mid-journey.
     */
    function product_index_url(): string
    {
        $host = trim((string) config('product.host', ''));

        return ($host !== '' && strcasecmp(request()->getHost(), $host) === 0)
            ? url('/')
            : route('product.index');
    }
}

if (! function_exists('product_main_url')) {
    /**
     * A path on the MAIN site, from a product page.
     *
     * The product domain serves only the storefront, so the main site's named routes cannot be built
     * there (Route::domain scoping) — links back have to be absolute. On the main site itself the
     * same path stays relative, so nothing bounces a visitor between hosts unnecessarily.
     */
    function product_main_url(string $path = ''): string
    {
        $host = trim((string) config('product.host', ''));
        $path = ltrim($path, '/');

        $onProductHost = $host !== '' && strcasecmp(request()->getHost(), $host) === 0;

        if (! $onProductHost) {
            return $path === '' ? '/' : '/'.$path;
        }

        $base = rtrim((string) config('product.main_url', config('app.url')), '/');

        return $path === '' ? $base.'/' : $base.'/'.$path;
    }
}
