<?php

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\AdShareTask;
use App\Models\AppReviewCampaign;
use App\Models\ReviewTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireTaskReservations extends Command
{
    protected $signature = 'tasks:expire-reservations';

    protected $description = 'Release expired app-review and ad-share task reservations';

    public function handle(): int
    {
        $expiredReviewCampaignIds = ReviewTask::query()->where('status', 'reserved')->where('expires_at', '<=', now())
            ->distinct()->pluck('app_review_campaign_id');
        foreach ($expiredReviewCampaignIds as $campaignId) {
            DB::transaction(function () use ($campaignId): void {
                $campaign = AppReviewCampaign::query()->lockForUpdate()->find($campaignId);
                if (! $campaign) return;
                $tasks = ReviewTask::query()->where('app_review_campaign_id', $campaign->id)
                    ->where('status', 'reserved')->where('expires_at', '<=', now())->lockForUpdate()->get();
                $tasks->each(fn (ReviewTask $task) => $task->update(['status' => 'expired']));
                $campaign->reserved_reviews = max(0, $campaign->reserved_reviews - $tasks->count());
                $campaign->save();
            });
        }

        $expiredAdIds = AdShareTask::query()->where('status', 'reserved')->where('expires_at', '<=', now())
            ->distinct()->pluck('ad_id');
        foreach ($expiredAdIds as $adId) {
            DB::transaction(function () use ($adId): void {
                $ad = Ad::query()->lockForUpdate()->find($adId);
                if (! $ad) return;
                $tasks = AdShareTask::query()->where('ad_id', $ad->id)
                    ->where('status', 'reserved')->where('expires_at', '<=', now())->lockForUpdate()->get();
                $tasks->each(fn (AdShareTask $task) => $task->update(['status' => 'expired']));
                $ad->reserved_share = max(0, $ad->reserved_share - $tasks->count());
                $ad->save();
            });
        }

        $this->info('Expired task reservations released.');

        return self::SUCCESS;
    }
}
