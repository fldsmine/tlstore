<?php

namespace App\Application\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin, testable wrapper around the official Telegram Bot API
 * (https://core.telegram.org/bots/api).
 *
 * Every method returns a TelegramResult instead of throwing, because a
 * Telegram outage must never break a withdrawal request. Failures are logged
 * with the bot token redacted — HTTP client exceptions embed the request URL,
 * which contains the token.
 */
final class TelegramClient
{
    public function __construct(private readonly TelegramSettings $settings) {}

    public function sendMessage(string $chatId, string $html, array $options = []): TelegramResult
    {
        return $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => (bool) ($options['disable_web_page_preview'] ?? true),
            'disable_notification' => (bool) ($options['disable_notification'] ?? false),
        ]);
    }

    public function sendPhoto(string $chatId, string $filePath, ?string $caption = null, array $options = []): TelegramResult
    {
        return $this->upload('sendPhoto', 'photo', $chatId, $filePath, $caption, $options);
    }

    public function sendDocument(string $chatId, string $filePath, ?string $caption = null, array $options = []): TelegramResult
    {
        return $this->upload('sendDocument', 'document', $chatId, $filePath, $caption, $options);
    }

    public function getMe(): TelegramResult
    {
        return $this->request('getMe', []);
    }

    public function getChat(string $chatId): TelegramResult
    {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    public function token(): ?string
    {
        return $this->settings->token();
    }

    public function configured(): bool
    {
        return $this->settings->configured();
    }

    /** @param array<string, mixed> $payload */
    private function request(string $method, array $payload): TelegramResult
    {
        $token = $this->token();
        if ($token === null) {
            return TelegramResult::failure('The Telegram bot token is not configured.');
        }

        $url = $this->url($token, $method);

        try {
            $response = $this->pending()->post($url, $payload);
        } catch (\Throwable $error) {
            return $this->connectionFailure($method, $error, $token);
        }

        return $this->interpret($method, $response->status(), $response->json(), $token);
    }

    private function upload(string $method, string $field, string $chatId, string $filePath, ?string $caption, array $options): TelegramResult
    {
        $token = $this->token();
        if ($token === null) {
            return TelegramResult::failure('The Telegram bot token is not configured.');
        }
        if (! is_file($filePath) || ! is_readable($filePath)) {
            return TelegramResult::failure('The attachment file is missing.');
        }

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            return TelegramResult::failure('The attachment file could not be opened.');
        }

        $fields = [
            'chat_id' => $chatId,
            'parse_mode' => 'HTML',
            'disable_notification' => (bool) ($options['disable_notification'] ?? false),
        ];
        if ($caption !== null && trim($caption) !== '') {
            $fields['caption'] = $this->clampCaption($caption);
        }

        try {
            $response = $this->pending()
                ->attach($field, $handle, basename($filePath))
                ->post($this->url($token, $method), $fields);
        } catch (\Throwable $error) {
            return $this->connectionFailure($method, $error, $token);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return $this->interpret($method, $response->status(), $response->json(), $token);
    }

    private function pending(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout((int) config('telegram.timeout', 15))
            ->connectTimeout((int) config('telegram.connect_timeout', 5));
    }

    private function url(string $token, string $method): string
    {
        return rtrim((string) config('telegram.api_base', 'https://api.telegram.org'), '/')
            .'/bot'.$token.'/'.$method;
    }

    /** Telegram rejects captions over 1024 characters; trim rather than fail. */
    private function clampCaption(string $caption): string
    {
        $limit = (int) config('telegram.caption_max_length', 1024);
        if (mb_strlen($caption) <= $limit) {
            return $caption;
        }

        return mb_substr($caption, 0, $limit - 1).'…';
    }

    private function interpret(string $method, int $status, mixed $json, string $token): TelegramResult
    {
        $payload = is_array($json) ? $json : [];
        $ok = $status >= 200 && $status < 300 && ($payload['ok'] ?? false) === true;

        if ($ok) {
            $messageId = data_get($payload, 'result.message_id');
            $messageId = is_numeric($messageId) ? (int) $messageId : null;

            return TelegramResult::success($messageId, $payload);
        }

        $description = data_get($payload, 'description');
        $error = is_string($description) && $description !== ''
            ? $description
            : 'Telegram rejected the request (HTTP '.$status.').';
        $retryAfter = data_get($payload, 'parameters.retry_after');
        $retryAfter = is_numeric($retryAfter) ? (int) $retryAfter : null;

        return $this->failure($method, $error, $retryAfter, $payload, $token);
    }

    private function connectionFailure(string $method, \Throwable $error, string $token): TelegramResult
    {
        return $this->failure($method, $error->getMessage(), null, [], $token);
    }

    /** @param array<string, mixed> $payload */
    private function failure(string $method, string $error, ?int $retryAfter, array $payload, string $token): TelegramResult
    {
        $safeError = str_replace([$token, urlencode($token)], '[redacted]', $error);

        Log::warning('telegram.request_failed', [
            'method' => $method,
            'error' => $safeError,
            'retry_after' => $retryAfter,
        ]);

        return TelegramResult::failure($safeError, $retryAfter, $payload);
    }
}
