<?php

namespace App\Application\Telegram;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Models\SystemSetting;

/**
 * Reads and writes the Telegram configuration.
 *
 * The bot token never leaves the server in clear text: it is encrypted with
 * APP_KEY before it is written to `system_settings`, and the admin console
 * only ever receives a masked preview. Environment variables
 * (`TELEGRAM_NOTIFY_BOT_TOKEN`, `TELEGRAM_NOTIFY_CHAT_ID`) act as defaults so
 * a deployment can configure the bot without touching the database.
 */
final class TelegramSettings
{
    public const TOKEN = 'telegram.bot_token';

    public const TOKEN_SET_AT = 'telegram.bot_token_set_at';

    public const CHAT_ID = 'telegram.chat_id';

    public const ENABLED = 'telegram.enabled';

    public const ANNOUNCEMENTS = 'telegram.admin_announcements.enabled';

    public const WITHDRAWALS = 'telegram.withdrawal_notifications.enabled';

    public const STATUS_UPDATES = 'telegram.withdrawal_status_updates.enabled';

    public const LAST_CHECK = 'telegram.last_check';

    /** @var array<string, mixed> */
    private array $loaded = [];

    private bool $hydrated = false;

    public function token(): ?string
    {
        $stored = $this->raw(self::TOKEN);
        if (is_string($stored) && trim($stored) !== '') {
            try {
                $token = Crypt::decryptString($stored);
                if (is_string($token) && trim($token) !== '') {
                    return trim($token);
                }
            } catch (\Throwable) {
                // APP_KEY rotated or the value was written by hand: fall back
                // to the environment instead of failing every delivery.
                Log::warning('telegram.token_decrypt_failed');
            }
        }

        $fallback = config('telegram.token');

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
    }

    public function chatId(): ?string
    {
        $stored = $this->raw(self::CHAT_ID);
        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        $fallback = config('telegram.chat_id');

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
    }

    public function configured(): bool
    {
        return $this->token() !== null && $this->chatId() !== null;
    }

    /**
     * Master switch. When it has never been saved it follows whether the bot
     * is configured, so an environment-only setup starts working immediately
     * while a bare install stays quiet.
     */
    public function enabled(): bool
    {
        $value = $this->raw(self::ENABLED);

        return $value === null ? $this->configured() : $this->toBool($value);
    }

    public function featureEnabled(string $key): bool
    {
        if (! $this->enabled() || ! $this->configured()) {
            return false;
        }

        $value = $this->raw($key);

        return $value === null ? true : $this->toBool($value);
    }

    public function announcementsEnabled(): bool
    {
        return $this->featureEnabled(self::ANNOUNCEMENTS);
    }

    public function withdrawalNotificationsEnabled(): bool
    {
        return $this->featureEnabled(self::WITHDRAWALS);
    }

    public function withdrawalStatusUpdatesEnabled(): bool
    {
        return $this->featureEnabled(self::STATUS_UPDATES);
    }

    /** `123456789:••••abcd` — enough to recognise the bot, never enough to use it. */
    public function maskedToken(): ?string
    {
        $token = $this->token();
        if ($token === null) {
            return null;
        }

        $parts = explode(':', $token, 2);
        $botId = preg_replace('/\D/', '', $parts[0]) ?: 'bot';
        $secret = $parts[1] ?? '';
        $tail = $secret !== '' ? substr($secret, -4) : '';

        return $botId.':••••••'.($tail !== '' ? $tail : '');
    }

    public function tokenFromEnvironment(): bool
    {
        $stored = $this->raw(self::TOKEN);

        return ! (is_string($stored) && trim($stored) !== '');
    }

    public function saveToken(string $token, ?int $adminId): void
    {
        $this->put(self::TOKEN, Crypt::encryptString(trim($token)), $adminId);
        $this->put(self::TOKEN_SET_AT, now()->toIso8601String(), $adminId);
    }

    public function saveChatId(?string $chatId, ?int $adminId): void
    {
        $this->put(self::CHAT_ID, $chatId !== null && trim($chatId) !== '' ? trim($chatId) : null, $adminId);
    }

    public function saveFlag(string $key, bool $value, ?int $adminId): void
    {
        $this->put($key, $value, $adminId);
    }

    public function recordCheck(string $summary, ?int $adminId = null): void
    {
        $this->put(self::LAST_CHECK, [
            'summary' => $summary,
            'checked_at' => now()->toIso8601String(),
        ], $adminId);
    }

    /** @return array{summary?: string, checked_at?: string}|null */
    public function lastCheck(): ?array
    {
        $value = $this->raw(self::LAST_CHECK);

        return is_array($value) ? $value : null;
    }

    public function forgetCachedValues(): void
    {
        $this->loaded = [];
        $this->hydrated = false;
    }

    private function put(string $key, mixed $value, ?int $adminId): void
    {
        SystemSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            ['value' => $value, 'is_public' => false, 'updated_by_admin_id' => $adminId],
        );
        $this->forgetCachedValues();
    }

    private function raw(string $key): mixed
    {
        $this->hydrate();

        return array_key_exists($key, $this->loaded) ? $this->loaded[$key] : null;
    }

    private function hydrate(): void
    {
        if ($this->hydrated) {
            return;
        }

        $this->hydrated = true;
        $keys = [
            self::TOKEN, self::TOKEN_SET_AT, self::CHAT_ID, self::ENABLED,
            self::ANNOUNCEMENTS, self::WITHDRAWALS, self::STATUS_UPDATES, self::LAST_CHECK,
        ];

        // A missing settings table (fresh clone before `migrate`) must not take
        // the withdrawal flow down: treat it as "not configured".
        try {
            $rows = SystemSetting::query()->whereIn('setting_key', $keys)->get();
        } catch (\Throwable) {
            $rows = collect();
        }

        foreach ($rows as $row) {
            $this->loaded[$row->setting_key] = $row->value;
        }
    }

    private function toBool(mixed $value): bool
    {
        if (is_array($value)) {
            $value = $value['enabled'] ?? $value['value'] ?? false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
