<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Validates Telegram WebApp initData exactly per the official algorithm
 * (https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app):
 *
 *   1. secret_key = HMAC_SHA256(bot_token, "WebAppData")
 *   2. data_check_string = initData pairs (minus `hash`), sorted by key,
 *      joined with "\n" as `key=value`
 *   3. hash must equal HMAC_SHA256(data_check_string, secret_key)
 *
 * On top of signature verification this service enforces:
 *   - freshness: auth_date must be within `telegram.auth_max_age` seconds;
 *   - presence of a well-formed `user` payload with a positive integer id.
 *
 * Replay protection is applied separately via consume() at the exact moments
 * something sensitive happens (token minted / identity bound), so a captured
 * payload cannot be replayed to mint more tokens or steal a link.
 *
 * The bot token is read from server config only — it is never accepted from
 * the client and never leaves the backend.
 */
class TelegramAuthService
{
    public const string TOKEN_NAME = 'telegram-mini-app';

    /**
     * Validate raw initData and return the authenticated Telegram user.
     *
     * @throws ValidationException when the payload is missing, malformed,
     *                             forged, stale, or replayed.
     */
    public function validate(string $initData): TelegramUser
    {
        $botToken = (string) config('services.telegram.bot_token', '');

        if ($botToken === '') {
            throw ValidationException::withMessages([
                'init_data' => 'Telegram authentication is not configured on this server.'],
            );
        }

        $fields = $this->parse($initData);

        $hash = $fields['hash'] ?? null;
        $authDate = (int) ($fields['auth_date'] ?? 0);
        $userJson = $fields['user'] ?? null;

        if (! is_string($hash) || $hash === '') {
            throw ValidationException::withMessages(['init_data' => 'Missing Telegram signature.']);
        }

        if ($authDate <= 0) {
            throw ValidationException::withMessages(['init_data' => 'Missing Telegram auth date.']);
        }

        if (! is_string($userJson) || $userJson === '') {
            throw ValidationException::withMessages(['init_data' => 'Missing Telegram user payload.']);
        }

        $this->assertFresh($authDate);
        $this->assertSignature($fields, $hash, $botToken);

        $user = json_decode($userJson, true);

        if (! is_array($user) || ! isset($user['id']) || (int) $user['id'] <= 0) {
            throw ValidationException::withMessages(['init_data' => 'Invalid Telegram user payload.']);
        }

        return TelegramUser::fromPayload($user, $authDate);
    }

    /** @return array<string, mixed> */
    private function parse(string $initData): array
    {
        $fields = [];
        parse_str($initData, $fields);

        return $fields;
    }

    private function assertFresh(int $authDate): void
    {
        $maxAge = (int) config('services.telegram.auth_max_age', 3600);

        if ($authDate > time() + 60) { // small clock-skew tolerance
            throw ValidationException::withMessages(['init_data' => 'Telegram auth data is not valid yet.']);
        }

        if ((time() - $authDate) > $maxAge) {
            throw ValidationException::withMessages(['init_data' => 'Telegram auth data has expired. Reopen the app and try again.']);
        }
    }

    /** @param  array<string, mixed>  $fields */
    private function assertSignature(array $fields, string $hash, string $botToken): void
    {
        unset($fields['hash']);

        $pairs = [];

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $pairs[] = $key.'='.(string) $value;
        }

        sort($pairs, SORT_STRING);

        $dataCheckString = implode("\n", $pairs);
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expected = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($expected, strtolower($hash))) {
            throw ValidationException::withMessages(['init_data' => 'Telegram signature verification failed.']);
        }
    }

    /**
     * Single-use guard, applied at the security-sensitive moments only: when
     * a bearer token is minted or an identity is bound. The first call marks
     * the initData as consumed for the rest of its validity window; anything
     * resubmitted afterwards is rejected, so a captured payload cannot be
     * replayed to mint more tokens or steal a link.
     *
     * @throws ValidationException when the payload was already consumed.
     */
    public function consume(string $initData): void
    {
        $maxAge = max(60, (int) config('services.telegram.auth_max_age', 3600));
        $key = 'telegram:init-data:'.hash('sha256', $initData);

        // add() only succeeds when the key does not exist yet.
        if (! Cache::add($key, 1, $maxAge)) {
            throw ValidationException::withMessages(['init_data' => 'This Telegram session was already used. Reopen the app and try again.']);
        }
    }
}
