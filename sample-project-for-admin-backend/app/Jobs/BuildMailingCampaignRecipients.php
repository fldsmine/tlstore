<?php

namespace App\Jobs;

use App\Enums\MailingCampaignStatus;
use App\Models\MailingCampaign;
use App\Services\Mailing\MailingRecipientAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BuildMailingCampaignRecipients implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $campaignId,
        public ?string $afterUserId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->campaignId.'|'.($this->afterUserId ?? 'start');
    }

    public int $uniqueFor = 3600;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(MailingRecipientAudience $audience): void
    {
        $campaign = MailingCampaign::query()->find($this->campaignId);
        if (! $campaign || $campaign->status === MailingCampaignStatus::CANCELLED) {
            return;
        }

        if ($campaign->status === MailingCampaignStatus::QUEUED) {
            if ($campaign->queued_count > 0) {
                QueueMailingCampaignRecipients::dispatch($campaign->id);
            }

            return;
        }
        if ($campaign->status !== MailingCampaignStatus::BUILDING) {
            return;
        }

        $chunkSize = max(1, min(2000, (int) config('mailing.audience_chunk_size', 500)));
        $query = $audience->campaignQuery($campaign->filters ?? [])
            ->select(['users.id', 'users.email'])
            ->orderBy('users.id');

        if ($this->afterUserId !== null) {
            $query->where('users.id', '>', $this->afterUserId);
        }

        $users = $query->limit($chunkSize)->get();
        $now = now();
        $rows = [];
        foreach ($users as $user) {
            if (! $audience->hasDeliverableAddress($user->email)) {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'campaign_id' => (string) $campaign->id,
                'user_id' => (string) $user->id,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $stillBuilding = DB::transaction(function () use ($campaign, $rows): bool {
            $locked = MailingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== MailingCampaignStatus::BUILDING) {
                return false;
            }

            $insertedCount = $rows === [] ? 0 : DB::table('mailing_campaign_recipients')->insertOrIgnore($rows);
            $locked->recipient_count += $insertedCount;
            if ($locked->recipient_count > (int) config('mailing.max_campaign_recipients', 250000)) {
                $locked->recipients()
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);
                $locked->queued_count = 0;
                $locked->cancelled_count = $locked->recipient_count;
                $locked->status = MailingCampaignStatus::FAILED;
                $locked->failure_code = 'AudienceLimitExceeded';
                $locked->completed_at = now();
            }
            $locked->save();

            return $locked->status === MailingCampaignStatus::BUILDING;
        });
        if (! $stillBuilding) {
            return;
        }

        if ($users->count() === $chunkSize) {
            self::dispatch($campaign->id, (string) $users->last()->id);

            return;
        }

        $nextStatus = DB::transaction(function () use ($campaign): ?MailingCampaignStatus {
            $locked = MailingCampaign::query()->whereKey($campaign->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === MailingCampaignStatus::CANCELLED) {
                return null;
            }
            if ($locked->status !== MailingCampaignStatus::BUILDING) {
                return $locked->status;
            }

            $recipientCount = $locked->recipient_count;
            $locked->queued_count = $recipientCount;
            $locked->sending_count = 0;
            $locked->sent_count = 0;
            $locked->failed_count = 0;
            $locked->cancelled_count = 0;
            $locked->failure_code = null;

            if ($recipientCount === 0) {
                $locked->status = MailingCampaignStatus::COMPLETED;
                $locked->completed_at = now();
            } elseif ($locked->scheduled_at !== null && $locked->scheduled_at->isFuture()) {
                $locked->status = MailingCampaignStatus::SCHEDULED;
                $locked->completed_at = null;
            } else {
                $locked->status = MailingCampaignStatus::QUEUED;
                $locked->completed_at = null;
            }

            $locked->save();

            return $locked->status;
        });

        if ($nextStatus === MailingCampaignStatus::QUEUED) {
            QueueMailingCampaignRecipients::dispatch($campaign->id);
        }
    }

    public function failed(Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $campaign = MailingCampaign::query()->whereKey($this->campaignId)->lockForUpdate()->first();
            if (! $campaign || $campaign->status !== MailingCampaignStatus::BUILDING) {
                return;
            }

            $cancelledCount = $campaign->recipients()
                ->where('status', 'pending')
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
            $campaign->recipient_count = max($campaign->recipient_count, $campaign->recipients()->count());
            $campaign->queued_count = 0;
            $campaign->cancelled_count = $cancelledCount;
            $campaign->status = MailingCampaignStatus::FAILED;
            $campaign->failure_code = substr(class_basename($exception), 0, 120);
            $campaign->completed_at = now();
            $campaign->save();
        });
    }
}
