<?php

namespace App\Application\Telegram;

use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Storage;

/**
 * Performs one delivery and records the outcome on the TelegramMessage row.
 *
 * Callers that need immediate feedback (the admin composer, the test message)
 * call deliver() directly; automatic notifications call it from a queue job so
 * Telegram latency never delays a user's request.
 */
final class TelegramDispatcher
{
    public function __construct(private readonly TelegramClient $client) {}

    public function deliver(TelegramMessage $message): TelegramResult
    {
        $message->forceFill(['status' => TelegramMessage::STATUS_SENDING])->save();

        $path = $this->localPath($message->attachment_path);
        $body = (string) $message->body;
        $disablePreview = (bool) data_get($message->meta, 'disable_web_page_preview', true);

        $result = match (true) {
            $path !== null && $message->attachment_type === 'photo' => $this->client->sendPhoto(
                (string) $message->chat_id, $path, $body, ['disable_notification' => true],
            ),
            $path !== null && $message->attachment_type === 'document' => $this->client->sendDocument(
                (string) $message->chat_id, $path, $body, ['disable_notification' => true],
            ),
            default => $this->client->sendMessage(
                (string) $message->chat_id, $body, ['disable_web_page_preview' => $disablePreview],
            ),
        };

        if ($result->ok) {
            $message->markSent($result->messageId);
            $this->cleanup($message);

            return $result;
        }

        // A missing attachment should degrade to a text message rather than
        // fail the whole delivery.
        if ($path !== null) {
            $fallback = $this->client->sendMessage(
                (string) $message->chat_id, $body, ['disable_web_page_preview' => $disablePreview],
            );
            if ($fallback->ok) {
                $message->markSent($fallback->messageId);
                $this->cleanup($message);

                return $fallback;
            }
        }

        $message->markFailed($result->error ?? 'Telegram rejected the message.');

        return $result;
    }

    public function isReady(): bool
    {
        return $this->client->configured();
    }

    private function localPath(?string $relative): ?string
    {
        if (! filled($relative)) {
            return null;
        }

        $path = Storage::disk('local')->path($relative);

        return is_file($path) ? $path : null;
    }

    /** Generated receipts are deleted once delivered; admin uploads are kept for the history view. */
    private function cleanup(TelegramMessage $message): void
    {
        if (! data_get($message->meta, 'delete_after_send', false) || ! filled($message->attachment_path)) {
            return;
        }

        try {
            Storage::disk('local')->delete((string) $message->attachment_path);
            $message->forceFill(['attachment_path' => null])->save();
        } catch (\Throwable) {
            // A leftover temp file is harmless; never fail a sent message over it.
        }
    }
}
