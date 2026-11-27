<?php

namespace App\Jobs;

use App\Application\Receipts\WithdrawalReceiptFile;
use App\Application\Telegram\TelegramDispatcher;
use App\Application\Telegram\TelegramNotifier;
use App\Application\Telegram\TelegramSettings;
use App\Exceptions\TelegramDeliveryException;
use App\Models\TelegramMessage;
use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Posts "NEW WITHDRAWAL REQUEST" with the generated receipt to the configured
 * Telegram chat. Queued after the withdrawal transaction commits, so a slow or
 * failing Telegram API never delays or rolls back the user's request.
 */
class SendWithdrawalTelegramNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 120, 600];

    public int $timeout = 120;

    public function __construct(public int $withdrawalId) {}

    public function uniqueId(): string
    {
        return 'withdrawal-request-'.$this->withdrawalId;
    }

    public function handle(
        TelegramSettings $settings,
        TelegramNotifier $notifier,
        TelegramDispatcher $dispatcher,
        WithdrawalReceiptFile $receipts,
    ): void {
        if (! $settings->withdrawalNotificationsEnabled()) {
            return;
        }

        $chatId = $settings->chatId();
        $withdrawal = WithdrawalRequest::query()
            ->with(['user:id,full_name,username', 'wallet:id,wallet_type', 'option:id,name'])
            ->find($this->withdrawalId);

        if ($withdrawal === null || $chatId === null) {
            return;
        }

        $message = TelegramMessage::query()->firstOrCreate(
            ['dedupe_key' => 'withdrawal-request:'.$withdrawal->id],
            [
                'kind' => TelegramMessage::KIND_WITHDRAWAL_REQUEST,
                'chat_id' => $chatId,
                'body' => $notifier->withdrawalRequest($withdrawal),
                'status' => TelegramMessage::STATUS_QUEUED,
                'withdrawal_request_id' => $withdrawal->id,
                'meta' => ['delete_after_send' => true, 'disable_web_page_preview' => true],
            ],
        );

        if ($message->status === TelegramMessage::STATUS_SENT) {
            return;
        }

        if (! $message->hasAttachment()) {
            $file = $receipts->make($withdrawal);
            if ($file !== null) {
                $message->forceFill([
                    'attachment_type' => $file['type'],
                    'attachment_path' => $file['relative'],
                ])->save();
            }
        }

        $result = $dispatcher->deliver($message);
        if ($result->failed()) {
            throw new TelegramDeliveryException($result->error ?? 'Telegram delivery failed.');
        }
    }

    public function failed(?\Throwable $error): void
    {
        $message = TelegramMessage::query()
            ->where('dedupe_key', 'withdrawal-request:'.$this->withdrawalId)
            ->first();

        if ($message === null) {
            return;
        }

        $message->markFailed($error?->getMessage() ?? 'Telegram delivery failed after retries.');
        app(WithdrawalReceiptFile::class)->delete($message->attachment_path);
        $message->forceFill(['attachment_path' => null])->save();

        Log::warning('telegram.withdrawal_notification_failed', [
            'withdrawal_id' => $this->withdrawalId,
            'error' => $error?->getMessage(),
        ]);
    }
}
