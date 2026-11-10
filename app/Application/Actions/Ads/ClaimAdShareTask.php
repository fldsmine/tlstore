<?php

namespace App\Application\Actions\Ads;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Application\Actions\Marketplace\DailyShareRevenue;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\Ad;
use App\Models\AdShareTask;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimAdShareTask
{
    public function __construct(private readonly ListingEligibility $eligibility) {}

    public function handle(User $user, Ad $ad, string $idempotencyKey): AdShareTask
    {
        return DB::transaction(function () use ($user, $ad, $idempotencyKey): AdShareTask {
            $ad = Ad::query()->lockForUpdate()->findOrFail($ad->id);

            // A user may claim a campaign only once. Return that assignment on
            // repeat taps/retries instead of attempting a second insert against
            // the unique (ad_id, user_id) constraint.
            $existing = AdShareTask::query()
                ->where('ad_id', $ad->id)
                ->where('user_id', $user->id)
                ->first();
            if ($existing) {
                return $existing->load('ad');
            }

            $subscription = $this->eligibility->subscriptionForTask($user, 'ad');
            if (! $subscription) {
                throw ValidationException::withMessages(['subscription' => 'Join an active ad sharing package before starting ad share tasks.']);
            }
            // Serialize claims under one active subscription so a user cannot
            // race several claims past the package's daily earnings cap.
            $subscription = UserSubscription::query()
                ->with(['plan.group'])
                ->lockForUpdate()
                ->findOrFail($subscription->id);
            $groupKey = $subscription->plan?->group?->group_key;
            if (in_array($subscription->plan?->group?->group_key, ['group_b', 'group_c'], true)
                && ! in_array($subscription->plan->group->group_key, $user->subscription_questionnaire_completed_groups ?? [], true)) {
                throw ValidationException::withMessages(['subscription_questionnaire' => 'Complete your app tester qualification questions before starting tasks.']);
            }
            $platform = strtolower(trim((string) $ad->type));
            if (str_contains($platform, 'twitter') || $platform === 'x') $platform = 'x';
            if (! in_array($platform, ['telegram', 'facebook', 'youtube', 'instagram', 'tiktok', 'x', 'whatsapp', 'others'], true)) $platform = 'others';
            if ($platform !== 'others' && ! $user->socialAccounts()->where('platform', $platform)->exists()) {
                throw ValidationException::withMessages(['social_account' => 'Add your '.$ad->type.' username or profile link before starting this task.']);
            }
            if (($subscription->plan?->max_ad_shares ?? 0) < 1) {
                throw ValidationException::withMessages(['subscription' => 'Your current package does not include ad shares. Join an ad sharing package to continue.']);
            }
            if ($groupKey === 'group_a' && $subscription->plan?->daily_revenue_minor !== null) {
                $atLimit = app(DailyShareRevenue::class)->hasReachedLimit($user, $subscription->plan);
                if ($atLimit === null) {
                    throw ValidationException::withMessages(['daily_revenue' => 'Your daily earnings could not be checked because an exchange rate is unavailable. Please try again later.']);
                }
                if ($atLimit) {
                    throw ValidationException::withMessages(['daily_revenue' => 'You have reached your package daily earnings limit. You can share ads again tomorrow.']);
                }
            }
            if ($ad->status->value !== 'active' || ($ad->starts_at && $ad->starts_at->isFuture()) || ($ad->ends_at && $ad->ends_at->isPast())) {
                throw ValidationException::withMessages(['ad' => 'This ad is not active.']);
            }

            $existingKey = AdShareTask::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existingKey) {
                if ($existingKey->user_id !== $user->id || $existingKey->ad_id !== $ad->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key has already been used.']);
                }
                return $existingKey->load('ad');
            }

            if ($ad->maximum_share !== null && $ad->total_share + $ad->reserved_share >= $ad->maximum_share) {
                throw ValidationException::withMessages(['ad' => 'This ad has no share slots remaining.']);
            }
            $rewardMinor = CurrencyMinorUnits::toMinor($ad->share_reward, $ad->currency);
            $budgetMinor = CurrencyMinorUnits::toMinor($ad->paid_amount, $ad->currency);
            if (($ad->total_share + $ad->reserved_share + 1) * $rewardMinor > $budgetMinor) {
                throw ValidationException::withMessages(['ad' => 'This ad has no reward budget remaining.']);
            }
            if ($subscription->plan?->max_ad_shares !== null) {
                $claimed = AdShareTask::query()->where('user_id', $user->id)
                    ->whereBetween('claimed_at', [$subscription->starts_at, $subscription->ends_at])->count();
                if ($claimed >= $subscription->plan->max_ad_shares) {
                    throw ValidationException::withMessages(['subscription' => 'Your plan ad share limit has been reached.']);
                }
            }
            if ($groupKey !== 'group_a' && $subscription->plan?->daily_task_limit !== null) {
                $todayReviews = \App\Models\ReviewTask::query()->where('reviewer_id', $user->id)->whereDate('claimed_at', today())->count();
                $todayShares = AdShareTask::query()->where('user_id', $user->id)->whereDate('claimed_at', today())->count();
                if ($todayReviews + $todayShares >= $subscription->plan->daily_task_limit) {
                    throw ValidationException::withMessages(['subscription' => 'Your package daily task limit has been reached.']);
                }
            }

            $task = AdShareTask::query()->create([
                'ad_id' => $ad->id,
                'user_id' => $user->id,
                'status' => 'reserved',
                'idempotency_key' => $idempotencyKey,
                'reserved_reward_minor' => $rewardMinor,
                'claimed_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);
            $ad->increment('reserved_share');

            return $task->load('ad');
        });
    }
}
