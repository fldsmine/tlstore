<?php

namespace App\Jobs;

use App\Application\Telegram\TelegramDispatcher;
use App\Exceptions\TelegramDeliveryException;
use App\Models\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generic delivery for messages already stored in `telegram_messages`.
 *
 * Delivery state lives in the row, so retries are idempotent: a message that
 * already reached `sent` is skipped. Failures are recorded by the dispatcher
 * and rethrown only to let the queue apply its backoff.
 */
class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 120, 600];

    public int $timeout = 60;

    public function __construct(public int $messageId) {}

    public function handle(TelegramDispatcher $dispatcher): void
    {
        $message = TelegramMessage::query()->find($this->messageId);
        if ($message === null || $message->status === TelegramMessage::STATUS_SENT) {
            return;
        }

        $result = $dispatcher->deliver($message);
        if ($result->failed()) {
            throw new TelegramDeliveryException($result->error ?? 'Telegram delivery failed.');
        }
    }

    public function failed(?\Throwable $error): void
    {
        TelegramMessage::query()->find($this->messageId)?->markFailed(
            $error?->getMessage() ?? 'Telegram delivery failed after retries.',
        );
    }
}
