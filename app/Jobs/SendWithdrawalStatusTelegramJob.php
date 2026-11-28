<?php

namespace App\Jobs;

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

/** Posts a paid / rejected / failed update for an existing withdrawal. */
class SendWithdrawalStatusTelegramJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 120, 600];

    public int $timeout = 60;

    public function __construct(public int $withdrawalId) {}

    public function uniqueId(): string
    {
        return 'withdrawal-status-'.$this->withdrawalId;
    }

    public function handle(
        TelegramSettings $settings,
        TelegramNotifier $notifier,
        TelegramDispatcher $dispatcher,
    ): void {
        if (! $settings->withdrawalStatusUpdatesEnabled()) {
            return;
        }

        $chatId = $settings->chatId();
        $withdrawal = WithdrawalRequest::query()
            ->with(['user:id,full_name,username', 'wallet:id,wallet_type', 'option:id,name'])
            ->find($this->withdrawalId);

        if ($withdrawal === null || $chatId === null) {
            return;
        }

        $status = is_object($withdrawal->status) ? $withdrawal->status->value : (string) $withdrawal->status;
        $message = TelegramMessage::query()->firstOrCreate(
            ['dedupe_key' => 'withdrawal-status:'.$withdrawal->id.':'.$status],
            [
                'kind' => TelegramMessage::KIND_WITHDRAWAL_STATUS,
                'chat_id' => $chatId,
                'body' => $notifier->withdrawalDecision($withdrawal),
                'status' => TelegramMessage::STATUS_QUEUED,
                'withdrawal_request_id' => $withdrawal->id,
                'meta' => ['disable_web_page_preview' => true],
            ],
        );

        if ($message->status === TelegramMessage::STATUS_SENT) {
            return;
        }

        $result = $dispatcher->deliver($message);
        if ($result->failed()) {
            throw new TelegramDeliveryException($result->error ?? 'Telegram delivery failed.');
        }
    }

    public function failed(?\Throwable $error): void
    {
        $message = TelegramMessage::query()
            ->where('withdrawal_request_id', $this->withdrawalId)
            ->where('kind', TelegramMessage::KIND_WITHDRAWAL_STATUS)
            ->latest('id')
            ->first();

        $message?->markFailed($error?->getMessage() ?? 'Telegram delivery failed after retries.');

        Log::warning('telegram.withdrawal_status_notification_failed', [
            'withdrawal_id' => $this->withdrawalId,
            'error' => $error?->getMessage(),
        ]);
    }
}
