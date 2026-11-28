<?php
// app\Application\Support\Helpers.php;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

function truncateEmail(string $email, int $visibleChars = 4): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }

    [$username, $domain] = explode('@', $email, 2);

    // Don't truncate very short usernames
    if (strlen($username) <= $visibleChars) {
        return $email;
    }

    return substr($username, 0, $visibleChars) . '...' . '@' . $domain;
}

if (! function_exists('getValue')) {
    function getValue(array|\Illuminate\Support\Collection $data, string $key, mixed $default = null): mixed
    {
        $value = data_get($data, $key);

        return $value === null || $value === '' ? $default : $value;
    }
}

function gs(?string $key = null, mixed $default = null): mixed
{
    $settings = null;

    try {
        // A cached empty array is a valid result: only `null` means "not cached yet".
        $settings = Cache::get('GeneralSetting');
    } catch (\Throwable) {
        // Cache store not available yet (e.g. `database` cache before its table
        // has been migrated). Fall through and read the settings directly.
        $settings = null;
    }

    if ($settings === null) {
        $settings = [];
        $cacheable = false;

        try {
            // Guards first installs, `:memory:` test databases and `migrate`
            // itself, where the settings table does not exist yet.
            if (Schema::hasTable('system_settings')) {
                $settings = SystemSetting::query()
                    ->where('is_public', true)
                    ->pluck('value', 'setting_key')
                    ->map(function ($value) {
                        if (! is_string($value)) {
                            return $value;
                        }

                        $trimmed = trim($value);

                        if (
                            (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
                            (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
                        ) {
                            $decoded = json_decode($value, true);

                            if (json_last_error() === JSON_ERROR_NONE) {
                                return $decoded;
                            }
                        }

                        return $value;
                    })
                    ->toArray();

                $cacheable = true;
            }
        } catch (\Throwable) {
            $settings = [];
        }

        if ($cacheable) {
            try {
                Cache::put('GeneralSetting', $settings, now()->addMinutes(30));
            } catch (\Throwable) {
                // Same as above: never let a cache backend take the app down.
            }
        }
    }

    if ($key === null) {
        return $settings;
    }

    return data_get($settings, $key, $default);
}
