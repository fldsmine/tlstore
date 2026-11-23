<?php

namespace App\Services\Telegram;

/**
 * Immutable snapshot of the Telegram user extracted from *validated* initData.
 * Only ever constructed by TelegramAuthService after the HMAC signature and
 * freshness checks pass — never from raw client input.
 */
final readonly class TelegramUser
{
    public function __construct(
        public int $id,
        public ?string $username,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $languageCode,
        public int $authDate,
    ) {}

    /** @param  array<string, mixed>  $user */
    public static function fromPayload(array $user, int $authDate): self
    {
        return new self(
            id: (int) ($user['id'] ?? 0),
            username: isset($user['username']) ? (string) $user['username'] : null,
            firstName: isset($user['first_name']) ? (string) $user['first_name'] : null,
            lastName: isset($user['last_name']) ? (string) $user['last_name'] : null,
            languageCode: isset($user['language_code']) ? (string) $user['language_code'] : null,
            authDate: $authDate,
        );
    }
}
