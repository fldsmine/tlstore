<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class TelegramBotUsername
{
    public function get(): ?string
    {
        $token = config('services.social.telegram_bot_token');
        if (! is_string($token) || trim($token) === '') {
            return null;
        }
        $configuredUsername = config('services.social.telegram_bot_username');
        $fallbackUsername = is_string($configuredUsername) && trim($configuredUsername) !== ''
            ? ltrim(trim($configuredUsername), '@')
            : null;

        // Token-specific caching prevents a rotated bot token from leaving
        // the widget pointed at a username belonging to different credentials.
        $cacheKey = 'auth.telegram.bot_username.'.substr(hash('sha256', $token), 0, 20);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($token, $fallbackUsername): ?string {
            try {
                $response = Http::timeout(6)->get("https://api.telegram.org/bot{$token}/getMe");
                $username = $response->json('result.username');

                if ($response->successful() && $response->json('ok') && is_string($username)) {
                    return $username;
                }
            } catch (\Throwable) {
                // The configured username still lets the login page work if
                // outbound requests to Telegram are temporarily unavailable.
            }

            return $fallbackUsername;
        });
    }
}
