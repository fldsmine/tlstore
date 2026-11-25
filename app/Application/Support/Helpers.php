<?php
// app\Application\Support\Helpers.php;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

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

function gs()
{
    $settings = Cache::get('GeneralSetting');
	if (!$settings) {
		$settings = SystemSetting::pluck('value', 'setting_key')->where('is_public', true)
			->map(function ($value) {
				if (!is_string($value)) {
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

        Cache::put('GeneralSetting', $settings);
    }
    return $settings;
}
