<?php

namespace App\Console\Commands;

use App\Enums\MailingCampaignStatus;
use App\Enums\MailingRecipientStatus;
use App\Jobs\DeliverMailingCampaignRecipient;
use App\Jobs\QueueMailingCampaignRecipients;
use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchScheduledMailingCampaigns extends Command
{
    protected $signature = 'mailing:dispatch-scheduled';

    protected $description = 'Queue due and recoverable mailing campaign recipients';

    public function handle(): int
    {
        $dueIds = MailingCampaign::query()
            ->where('status', MailingCampaignStatus::SCHEDULED->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(500)
            ->pluck('id');

        foreach ($dueIds as $id) {
            DB::transaction(function () use ($id): void {
                $campaign = MailingCampaign::query()->whereKey($id)->lockForUpdate()->first();
                if (! $campaign
                    || $campaign->status !== MailingCampaignStatus::SCHEDULED
                    || $campaign->scheduled_at === null
                    || $campaign->scheduled_at->isFuture()) {
                    return;
                }

                $campaign->status = MailingCampaignStatus::QUEUED;
                $campaign->save();
            });
        }

        $this->recoverStaleDeliveryJobs();

        // Coordinators are idempotent and fill only available global/per-campaign queue slots.
        // The minute tick retries campaigns whose queue dispatch previously failed or was lost.
        MailingCampaign::query()
            ->whereIn('status', [MailingCampaignStatus::QUEUED->value, MailingCampaignStatus::SENDING->value])
            ->where('queued_count', '>', 0)
            ->whereHas('recipients', fn ($recipients) => $recipients->where('status', MailingRecipientStatus::PENDING->value))
            ->orderBy('updated_at')
            ->limit(500)
            ->pluck('id')
            ->each(fn ($id) => $this->dispatchCampaign((string) $id));

        return self::SUCCESS;
    }

    private function recoverStaleDeliveryJobs(): void
    {
        $connection = (string) config('queue.default', 'database');
        $retryAfter = max(30, (int) config('queue.connections.'.$connection.'.retry_after', 90));
        $staleSendingBefore = now()->subSeconds(max(300, $retryAfter + 60));
        $staleQueuedBefore = now()->subMinutes(5);

        MailingCampaignRecipient::query()
            ->where(function ($recipients) use ($staleQueuedBefore, $staleSendingBefore): void {
                $recipients->where(function ($queued) use ($staleQueuedBefore): void {
                    $queued->whereIn('status', [MailingRecipientStatus::QUEUED->value, MailingRecipientStatus::RETRYING->value])
                        ->whereNotNull('available_at')
                        ->where('available_at', '<=', $staleQueuedBefore);
                })->orWhere(function ($sending) use ($staleSendingBefore): void {
                    $sending->where('status', MailingRecipientStatus::SENDING->value)
                        ->whereNotNull('last_attempt_at')
                        ->where('last_attempt_at', '<=', $staleSendingBefore);
                });
            })
            ->whereHas('campaign', fn ($campaigns) => $campaigns->whereIn('status', [
                MailingCampaignStatus::QUEUED->value,
                MailingCampaignStatus::SENDING->value,
            ]))
            ->orderBy('updated_at')
            ->limit(200)
            ->pluck('id')
            ->each(function ($recipientId): void {
                try {
                    DeliverMailingCampaignRecipient::dispatch((string) $recipientId, 300);
                } catch (Throwable $exception) {
                    Log::warning('Stale mailing recipient could not be requeued.', [
                        'recipient_id' => $recipientId,
                        'error_type' => class_basename($exception),
                    ]);
                }
            });
    }

    private function dispatchCampaign(string $campaignId): void
    {
        try {
            QueueMailingCampaignRecipients::dispatch($campaignId);
        } catch (Throwable $exception) {
            Log::warning('Scheduled mailing campaign coordinator could not be queued.', [
                'campaign_id' => $campaignId,
                'error_type' => class_basename($exception),
            ]);
        }
    }
}
