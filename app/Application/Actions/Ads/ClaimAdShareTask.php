<?php

namespace App\Application\Actions\Ads;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\Ad;
use App\Models\AdShareTask;
use App\Models\User;
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

            $subscription = $this->eligibility->activeMarketplaceSubscription($user);
            if (! $subscription) {
                throw ValidationException::withMessages(['subscription' => 'Subscribe to a marketplace plan before starting ad share tasks.']);
            }
            if (! $this->eligibility->canAccess($user, $ad->location)) {
                throw ValidationException::withMessages(['location_coverage' => 'This ad is outside your current location coverage. Purchase a location add-on to share it.']);
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
