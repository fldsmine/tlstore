<?php

namespace App\Application\Actions\Reviews;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Console\Support\TaskRewardCalculator;
use App\Models\AppReviewCampaign;
use App\Models\ReviewTask;
use App\Models\ReviewTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimReviewTask
{
    public function __construct(
        private readonly ListingEligibility $eligibility,
        private readonly TaskRewardCalculator $rewards,
    ) {}

    public function handle(User $user, AppReviewCampaign $campaign, string $idempotencyKey): ReviewTask
    {
        return DB::transaction(function () use ($user, $campaign, $idempotencyKey): ReviewTask {
            $campaign = AppReviewCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $subscription = $this->eligibility->subscriptionForTask(
                $user,
                'review',
                $campaign->app?->store,
                $campaign->app?->category,
                true,
            );

            if (! $subscription) {
                throw ValidationException::withMessages(['subscription' => 'Join an active app review package for this store before starting review tasks.']);
            }
            if (! $this->eligibility->appAllowed($user, $campaign->location, $campaign->app?->store, $campaign->app?->category)) {
                if (in_array($subscription->plan?->group?->group_key, ['group_b', 'group_c'], true)
                    && ! in_array($subscription->plan->group->group_key, $user->subscription_questionnaire_completed_groups ?? [], true)) {
                    throw ValidationException::withMessages(['subscription_questionnaire' => 'Complete your app tester qualification questions before starting tasks.']);
                }
            }
            if (($subscription->plan?->max_review_tasks ?? 0) < 1) {
                throw ValidationException::withMessages(['subscription' => 'Your current package does not include app reviews. Join a review package to continue.']);
            }
            if ($campaign->status->value !== 'active' || ($campaign->starts_at && $campaign->starts_at->isFuture()) || ($campaign->ends_at && $campaign->ends_at->isPast())) {
                throw ValidationException::withMessages(['campaign' => 'This campaign is not active.']);
            }

            $existing = ReviewTask::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->reviewer_id !== $user->id || $existing->app_review_campaign_id !== $campaign->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key has already been used.']);
                }
                return $existing;
            }

            if (ReviewTask::query()->where('app_review_campaign_id', $campaign->id)->where('reviewer_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['campaign' => 'You have already claimed this campaign.']);
            }

            if ($campaign->maximum_review !== null && $campaign->completed_reviews + $campaign->reserved_reviews >= $campaign->maximum_review) {
                throw ValidationException::withMessages(['campaign' => 'This campaign has no review slots remaining.']);
            }
            if (($campaign->completed_reviews + $campaign->reserved_reviews + 1) * $campaign->review_reward_minor > $campaign->paid_amount_minor) {
                throw ValidationException::withMessages(['campaign' => 'This campaign has no reward budget remaining.']);
            }
            if ($subscription->plan?->max_review_tasks !== null) {
                $claimedQuery = ReviewTask::query()->where('reviewer_id', $user->id)
                    ->whereBetween('claimed_at', [$subscription->starts_at, $subscription->ends_at]);
                if ($subscription->plan->eligible_app_store) {
                    $claimedQuery->whereHas('campaign.app', fn ($query) => $query->where('store', $subscription->plan->eligible_app_store));
                }
                if ($subscription->plan->eligible_app_category) {
                    $claimedQuery->whereHas('campaign.app', fn ($query) => $query->where('category', $subscription->plan->eligible_app_category));
                }
                $claimed = $claimedQuery->count();
                if ($claimed >= $subscription->plan->max_review_tasks) {
                    throw ValidationException::withMessages(['subscription' => 'Your plan review task limit has been reached.']);
                }
            }
            if ($subscription->plan?->daily_task_limit !== null) {
                $todayReviewsQuery = ReviewTask::query()->where('reviewer_id', $user->id)->whereDate('claimed_at', today());
                if ($subscription->plan->eligible_app_store) {
                    $todayReviewsQuery->whereHas('campaign.app', fn ($query) => $query->where('store', $subscription->plan->eligible_app_store));
                }
                if ($subscription->plan->eligible_app_category) {
                    $todayReviewsQuery->whereHas('campaign.app', fn ($query) => $query->where('category', $subscription->plan->eligible_app_category));
                }
                $todayReviews = $todayReviewsQuery->count();
                $todayShares = ($subscription->plan->max_ad_shares ?? 0) > 0
                    ? \App\Models\AdShareTask::query()->where('user_id', $user->id)->whereDate('claimed_at', today())->count()
                    : 0;
                if ($todayReviews + $todayShares >= $subscription->plan->daily_task_limit) {
                    throw ValidationException::withMessages(['subscription' => 'Your package daily task limit has been reached.']);
                }
            }

            $templateVersion = $campaign->reviewTemplateVersion;
            if (! $templateVersion) {
                $templateVersion = ReviewTemplateVersion::query()->where('status', 'published')
                    ->whereNotNull('published_at')->latest('published_at')->first();
            }
            if (! $templateVersion) {
                throw ValidationException::withMessages(['campaign' => 'No published review questionnaire is configured.']);
            }

            $rewardMinor = $this->rewards->forReview(
                (int) $campaign->review_reward_minor,
                (string) $campaign->currency,
                $subscription->plan,
            );
            if ($rewardMinor === null) {
                throw ValidationException::withMessages(['reward' => 'The package review reward cannot be converted to the listing currency right now.']);
            }
            if ($rewardMinor < 1) {
                throw ValidationException::withMessages(['reward' => 'The active package does not have a valid per-review reward.']);
            }

            $task = ReviewTask::query()->create([
                'app_review_campaign_id' => $campaign->id,
                'reviewer_id' => $user->id,
                'review_template_version_id' => $templateVersion->id,
                'status' => 'reserved',
                'idempotency_key' => $idempotencyKey,
                'reserved_reward_minor' => $rewardMinor,
                'claimed_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
            $campaign->increment('reserved_reviews');

            return $task->load('campaign.app.screenshots', 'templateVersion.questions');
        });
    }
}
