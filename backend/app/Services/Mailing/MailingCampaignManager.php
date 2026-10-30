<?php

namespace App\Services\Mailing;

use App\Enums\MailingCampaignStatus;
use App\Enums\MailingRecipientStatus;
use App\Jobs\BuildMailingCampaignRecipients;
use App\Jobs\QueueMailingCampaignRecipients;
use App\Models\MailingCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

class MailingCampaignManager
{
    public function __construct(
        private readonly MailingInfrastructureHealth $health,
        private readonly MailingRecipientAudience $audience,
    ) {}

    public function launch(MailingCampaign $campaign): MailingCampaign
    {
        $this->health->assertReady();
        if ($campaign->status !== MailingCampaignStatus::DRAFT) {
            throw new LogicException('Only a draft campaign can be launched.');
        }
        if ($campaign->scheduled_at !== null && $campaign->scheduled_at->isPast()) {
            throw new LogicException('Choose a future schedule time, or clear the schedule to send now.');
        }

        $recipientCount = $this->audience->countDeliverable($campaign->filters ?? []);
        if ($recipientCount < 1) {
            throw new LogicException('No eligible recipients match this audience. Update the filters before launching.');
        }
        if ($recipientCount > (int) config('mailing.max_campaign_recipients', 250000)) {
            throw new LogicException('This audience exceeds the configured campaign limit. Narrow the filters or raise the limit after review.');
        }

        $campaign->update([
            'status' => MailingCampaignStatus::BUILDING,
            'recipient_count' => 0,
            'queued_count' => 0,
            'sending_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'cancelled_count' => 0,
            'started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'failure_code' => null,
        ]);

        try {
            BuildMailingCampaignRecipients::dispatch((string) $campaign->id);
        } catch (Throwable $exception) {
            $campaign->update([
                'status' => MailingCampaignStatus::DRAFT,
                'failure_code' => 'QueueUnavailable',
            ]);
            Log::warning('Could not queue mailing campaign audience preparation.', [
                'campaign_id' => $campaign->id,
                'error_type' => class_basename($exception),
            ]);

            throw new MailingConfigurationException('The campaign could not be queued. Verify the queue connection and try again.');
        }

        return $campaign->refresh();
    }

    public function cancel(string $campaignId): MailingCampaign
    {
        return DB::transaction(function () use ($campaignId): MailingCampaign {
            $campaign = MailingCampaign::query()->whereKey($campaignId)->lockForUpdate()->firstOrFail();
            if (! $campaign->status->isActive()) {
                throw new LogicException('Only an active campaign can be cancelled.');
            }

            $cancelledRecipients = $campaign->recipients()
                ->whereIn('status', [MailingRecipientStatus::PENDING->value, MailingRecipientStatus::QUEUED->value, MailingRecipientStatus::RETRYING->value])
                ->update([
                    'status' => MailingRecipientStatus::CANCELLED->value,
                    'updated_at' => now(),
                ]);

            $campaign->recipient_count = max($campaign->recipient_count, $campaign->recipients()->count());
            $campaign->queued_count = max(0, $campaign->queued_count - $cancelledRecipients);
            $campaign->cancelled_count += $cancelledRecipients;
            $campaign->status = MailingCampaignStatus::CANCELLED;
            $campaign->cancelled_at = now();
            $campaign->completed_at = now();
            $campaign->save();

            return $campaign;
        });
    }

    public function retryFailed(string $campaignId): int
    {
        $this->health->assertReady();

        $retryCount = DB::transaction(function () use ($campaignId): int {
            $campaign = MailingCampaign::query()->whereKey($campaignId)->lockForUpdate()->firstOrFail();
            if (! in_array($campaign->status, [MailingCampaignStatus::COMPLETED_WITH_ERRORS, MailingCampaignStatus::FAILED], true)) {
                throw new LogicException('Failed recipients can only be retried after the campaign has finished.');
            }

            $retryCount = $campaign->recipients()
                ->where('status', MailingRecipientStatus::FAILED->value)
                ->update([
                    'status' => MailingRecipientStatus::PENDING->value,
                    'last_error_code' => null,
                    'sent_at' => null,
                    'available_at' => null,
                    'updated_at' => now(),
                ]);

            if ($retryCount === 0) {
                throw new LogicException('This campaign has no failed recipients to retry.');
            }

            $campaign->failed_count = max(0, $campaign->failed_count - $retryCount);
            $campaign->queued_count += $retryCount;
            $campaign->completed_at = null;
            $campaign->failure_code = null;
            $campaign->status = MailingCampaignStatus::QUEUED;
            $campaign->save();

            return $retryCount;
        });

        try {
            QueueMailingCampaignRecipients::dispatch($campaignId);
        } catch (Throwable $exception) {
            // Keep the rows queued: the minute scheduler will re-fan them out after the queue recovers.
            Log::warning('Could not immediately queue retried mailing recipients.', [
                'campaign_id' => $campaignId,
                'error_type' => class_basename($exception),
            ]);

            throw new MailingConfigurationException('The failed recipients are marked for retry, but dispatch failed. The scheduled campaign worker will retry when the queue is available.');
        }

        return $retryCount;
    }
}
