<?php

namespace App\Services\Mailing;

use App\Enums\MailingCampaignStatus;
use App\Enums\MailingRecipientStatus;
use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use Illuminate\Support\Facades\DB;
use Throwable;

class MailingCampaignDeliveryState
{
    /**
     * Atomically claim a queued recipient. The campaign row is locked first everywhere so
     * cancellation, retries and workers follow the same lock order.
     *
     * @return array{campaign_id:string,user_id:?string,busy?:bool,retry_after?:int}|null
     */
    public function begin(string $recipientId): ?array
    {
        $reference = MailingCampaignRecipient::query()->find($recipientId);
        if (! $reference) {
            return null;
        }

        return DB::transaction(function () use ($reference, $recipientId): ?array {
            $campaign = MailingCampaign::query()->whereKey($reference->campaign_id)->lockForUpdate()->first();
            $recipient = MailingCampaignRecipient::query()->whereKey($recipientId)->lockForUpdate()->first();
            if (! $campaign || ! $recipient) {
                return null;
            }

            $status = $recipient->status;
            if (in_array($status, [MailingRecipientStatus::SENT, MailingRecipientStatus::FAILED, MailingRecipientStatus::CANCELLED], true)) {
                return null;
            }

            if ($campaign->status === MailingCampaignStatus::CANCELLED) {
                if (in_array($status, [MailingRecipientStatus::PENDING, MailingRecipientStatus::QUEUED, MailingRecipientStatus::RETRYING], true)) {
                    $recipient->status = MailingRecipientStatus::CANCELLED;
                    $recipient->save();
                    $campaign->queued_count = max(0, $campaign->queued_count - 1);
                    $campaign->cancelled_count++;
                    $campaign->save();
                }

                return null;
            }

            if (! in_array($campaign->status, [MailingCampaignStatus::QUEUED, MailingCampaignStatus::SENDING], true)) {
                return null;
            }

            if ($status === MailingRecipientStatus::SENDING) {
                $queueName = (string) config('queue.default', 'database');
                $retryAfter = max(300, (int) config('queue.connections.'.$queueName.'.retry_after', 90));
                if ($recipient->last_attempt_at !== null && $recipient->last_attempt_at->gt(now()->subSeconds($retryAfter))) {
                    $elapsed = max(0, now()->timestamp - $recipient->last_attempt_at->timestamp);

                    return [
                        'campaign_id' => (string) $campaign->id,
                        'user_id' => $recipient->user_id !== null ? (string) $recipient->user_id : null,
                        'busy' => true,
                        'retry_after' => max(5, $retryAfter - $elapsed),
                    ];
                }
            }

            if (in_array($status, [MailingRecipientStatus::PENDING, MailingRecipientStatus::QUEUED, MailingRecipientStatus::RETRYING], true)) {
                $campaign->queued_count = max(0, $campaign->queued_count - 1);
                $campaign->sending_count++;
                $recipient->status = MailingRecipientStatus::SENDING;
            }

            // A reservation timeout may redeliver a job left in `sending`; the same unique job
            // resumes it. A hard crash after remote acceptance remains the unavoidable transport
            // acknowledgement window of any SMTP/API sender.
            $recipient->attempts++;
            $recipient->last_attempt_at = now();
            $recipient->available_at = null;
            $recipient->last_error_code = null;
            $recipient->save();

            if ($campaign->status === MailingCampaignStatus::QUEUED) {
                $campaign->status = MailingCampaignStatus::SENDING;
            }
            $campaign->started_at ??= now();
            $campaign->save();

            return [
                'campaign_id' => (string) $campaign->id,
                'user_id' => $recipient->user_id !== null ? (string) $recipient->user_id : null,
            ];
        });
    }

    public function succeeded(string $recipientId): void
    {
        DB::transaction(function () use ($recipientId): void {
            $reference = MailingCampaignRecipient::query()->find($recipientId);
            if (! $reference) {
                return;
            }

            $campaign = MailingCampaign::query()->whereKey($reference->campaign_id)->lockForUpdate()->first();
            $recipient = MailingCampaignRecipient::query()->whereKey($recipientId)->lockForUpdate()->first();
            if (! $campaign || ! $recipient || $recipient->status !== MailingRecipientStatus::SENDING) {
                return;
            }

            $recipient->status = MailingRecipientStatus::SENT;
            $recipient->sent_at = now();
            $recipient->last_error_code = null;
            $recipient->save();

            $campaign->sending_count = max(0, $campaign->sending_count - 1);
            $campaign->sent_count++;
            $campaign->save();
            $this->finishIfTerminal($campaign);
        });
    }

    public function retrying(string $recipientId, Throwable $exception, int $retryAfterSeconds = 60): void
    {
        DB::transaction(function () use ($recipientId, $exception): void {
            $reference = MailingCampaignRecipient::query()->find($recipientId);
            if (! $reference) {
                return;
            }

            $campaign = MailingCampaign::query()->whereKey($reference->campaign_id)->lockForUpdate()->first();
            $recipient = MailingCampaignRecipient::query()->whereKey($recipientId)->lockForUpdate()->first();
            if (! $campaign || ! $recipient || $recipient->status !== MailingRecipientStatus::SENDING) {
                return;
            }

            $recipient->status = MailingRecipientStatus::RETRYING;
            $recipient->available_at = now()->addSeconds(max(1, $retryAfterSeconds));
            $recipient->last_error_code = $this->errorCode($exception);
            $recipient->save();

            $campaign->sending_count = max(0, $campaign->sending_count - 1);
            $campaign->queued_count++;
            $campaign->save();
        });
    }

    public function failed(string $recipientId, Throwable $exception): void
    {
        DB::transaction(function () use ($recipientId, $exception): void {
            $reference = MailingCampaignRecipient::query()->find($recipientId);
            if (! $reference) {
                return;
            }

            $campaign = MailingCampaign::query()->whereKey($reference->campaign_id)->lockForUpdate()->first();
            $recipient = MailingCampaignRecipient::query()->whereKey($recipientId)->lockForUpdate()->first();
            if (! $campaign || ! $recipient || in_array($recipient->status, [MailingRecipientStatus::SENT, MailingRecipientStatus::FAILED, MailingRecipientStatus::CANCELLED], true)) {
                return;
            }

            if ($recipient->status === MailingRecipientStatus::SENDING) {
                $campaign->sending_count = max(0, $campaign->sending_count - 1);
            } elseif (in_array($recipient->status, [MailingRecipientStatus::PENDING, MailingRecipientStatus::QUEUED, MailingRecipientStatus::RETRYING], true)) {
                $campaign->queued_count = max(0, $campaign->queued_count - 1);
            }

            $recipient->status = MailingRecipientStatus::FAILED;
            $recipient->available_at = null;
            $recipient->last_error_code = $this->errorCode($exception);
            $recipient->save();

            $campaign->failed_count++;
            $campaign->failure_code = $this->errorCode($exception);
            $campaign->save();
            $this->finishIfTerminal($campaign);
        });
    }

    public function failPermanently(string $recipientId, string $failureCode): void
    {
        DB::transaction(function () use ($recipientId, $failureCode): void {
            $reference = MailingCampaignRecipient::query()->find($recipientId);
            if (! $reference) {
                return;
            }

            $campaign = MailingCampaign::query()->whereKey($reference->campaign_id)->lockForUpdate()->first();
            $recipient = MailingCampaignRecipient::query()->whereKey($recipientId)->lockForUpdate()->first();
            if (! $campaign || ! $recipient || in_array($recipient->status, [MailingRecipientStatus::SENT, MailingRecipientStatus::FAILED, MailingRecipientStatus::CANCELLED], true)) {
                return;
            }

            if ($recipient->status === MailingRecipientStatus::SENDING) {
                $campaign->sending_count = max(0, $campaign->sending_count - 1);
            } elseif (in_array($recipient->status, [MailingRecipientStatus::PENDING, MailingRecipientStatus::QUEUED, MailingRecipientStatus::RETRYING], true)) {
                $campaign->queued_count = max(0, $campaign->queued_count - 1);
            }

            $recipient->status = MailingRecipientStatus::FAILED;
            $recipient->available_at = null;
            $recipient->last_error_code = $failureCode;
            $recipient->save();

            $campaign->failed_count++;
            $campaign->failure_code = $failureCode;
            $campaign->save();
            $this->finishIfTerminal($campaign);
        });
    }

    private function finishIfTerminal(MailingCampaign $campaign): void
    {
        if ($campaign->status === MailingCampaignStatus::CANCELLED
            || $campaign->queued_count > 0
            || $campaign->sending_count > 0
            || $campaign->recipient_count === 0) {
            return;
        }

        $campaign->status = $campaign->failed_count > 0
            ? MailingCampaignStatus::COMPLETED_WITH_ERRORS
            : MailingCampaignStatus::COMPLETED;
        $campaign->completed_at = now();
        $campaign->save();
    }

    private function errorCode(Throwable $exception): string
    {
        return substr(class_basename($exception), 0, 120);
    }
}
