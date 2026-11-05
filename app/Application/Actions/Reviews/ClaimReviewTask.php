<?php

namespace App\Application\Actions\Reviews;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Models\AppReviewCampaign;
use App\Models\ReviewTask;
use App\Models\ReviewTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimReviewTask
{
    public function __construct(private readonly ListingEligibility $eligibility) {}

    public function handle(User $user, AppReviewCampaign $campaign, string $idempotencyKey): ReviewTask
    {
        return DB::transaction(function () use ($user, $campaign, $idempotencyKey): ReviewTask {
            $campaign = AppReviewCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $subscription = $this->eligibility->activeMarketplaceSubscription($user);

            if (! $subscription) {
                throw ValidationException::withMessages(['subscription' => 'Subscribe to a marketplace plan before starting review tasks.']);
            }
            if (! $this->eligibility->canAccess($user, $campaign->location)) {
                throw ValidationException::withMessages(['location_coverage' => 'This app is outside your current location coverage. Purchase a location add-on to review it.']);
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
                $claimed = ReviewTask::query()->where('reviewer_id', $user->id)
                    ->whereBetween('claimed_at', [$subscription->starts_at, $subscription->ends_at])->count();
                if ($claimed >= $subscription->plan->max_review_tasks) {
                    throw ValidationException::withMessages(['subscription' => 'Your plan review task limit has been reached.']);
                }
            }

            $templateVersion = ReviewTemplateVersion::query()->where('status', 'published')
                ->whereNotNull('published_at')->latest('published_at')->first();
            if (! $templateVersion) {
                throw ValidationException::withMessages(['campaign' => 'No published review questionnaire is configured.']);
            }

            $task = ReviewTask::query()->create([
                'app_review_campaign_id' => $campaign->id,
                'reviewer_id' => $user->id,
                'review_template_version_id' => $templateVersion->id,
                'status' => 'reserved',
                'idempotency_key' => $idempotencyKey,
                'reserved_reward_minor' => $campaign->review_reward_minor,
                'claimed_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
            $campaign->increment('reserved_reviews');

            return $task->load('campaign.app.screenshots', 'templateVersion.questions');
        });
    }
}
