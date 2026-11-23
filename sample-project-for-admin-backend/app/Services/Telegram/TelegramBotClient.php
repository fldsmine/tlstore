<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Bot API client for outbound DMs (verification codes etc).
 * The token never leaves the server; failures degrade gracefully so the
 * caller can fall back to email delivery.
 */
class TelegramBotClient
{
    public function isConfigured(): bool
    {
        return (string) config('services.telegram.bot_token', '') !== '';
    }

    /**
     * Send a plain-text DM. False when unconfigured or delivery fails.
     * $extra passes additional sendMessage fields through untouched
     * (e.g. ['reply_markup' => [...]] for inline keyboards).
     */
    public function sendMessage(int $chatId, string $text, array $extra = []): bool
    {
        return $this->sendMessageResult($chatId, $text, $extra)['ok'];
    }

    /**
     * Same as sendMessage, with the Bot API message_id (when Telegram accepted
     * the DM) so the backoffice can keep a conversation thread.
     *
     * @return array{ok: bool, message_id: int|null, error: string|null}
     */
    public function sendMessageResult(int $chatId, string $text, array $extra = []): array
    {
        $token = (string) config('services.telegram.bot_token', '');

        if ($token === '') {
            return ['ok' => false, 'message_id' => null, 'error' => 'Telegram bot is not configured.'];
        }

        try {
            $response = Http::timeout(8)->post("https://api.telegram.org/bot{$token}/sendMessage", array_merge([
                'chat_id' => $chatId,
                'text' => $text,
            ], $extra));

            if ($response->successful() && $response->json('ok') === true) {
                $messageId = $response->json('result.message_id');

                return [
                    'ok' => true,
                    'message_id' => is_numeric($messageId) ? (int) $messageId : null,
                    'error' => null,
                ];
            }

            $error = (string) ($response->json('description') ?: 'Telegram rejected the message.');

            Log::warning('Telegram sendMessage rejected: '.$error);

            return ['ok' => false, 'message_id' => null, 'error' => $error];
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed: '.$e->getMessage());

            return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }
}
