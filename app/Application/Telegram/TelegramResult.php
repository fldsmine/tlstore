<?php

namespace App\Application\Telegram;

/** Outcome of one Telegram Bot API call. Never throws: callers decide. */
final class TelegramResult
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        public readonly bool $ok,
        public readonly ?int $messageId = null,
        public readonly ?string $error = null,
        public readonly ?int $retryAfter = null,
        public readonly array $payload = [],
    ) {}

    /** @param array<string, mixed> $payload */
    public static function success(?int $messageId = null, array $payload = []): self
    {
        return new self(true, $messageId, null, null, $payload);
    }

    /** @param array<string, mixed> $payload */
    public static function failure(string $error, ?int $retryAfter = null, array $payload = []): self
    {
        return new self(false, null, $error, $retryAfter, $payload);
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    public function isRateLimited(): bool
    {
        return $this->retryAfter !== null && $this->retryAfter > 0;
    }

    /** Telegram's `result` payload, e.g. the getMe/getChat objects. */
    public function result(): array
    {
        $result = $this->payload['result'] ?? [];

        return is_array($result) ? $result : [];
    }
}
