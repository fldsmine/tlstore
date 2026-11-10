<?php

namespace App\Features\Marketplace\Http\Controllers\Api\V1;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Application\Actions\Marketplace\ListEligibleListings;
use App\Application\Actions\Marketplace\DailyShareRevenue;
use App\Models\Ad;
use App\Models\AdShareTask;
use App\Models\AppReviewCampaign;
use App\Models\ReviewTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DailyTaskController
{
    public function index(
        Request $request,
        string $type,
        ListEligibleListings $listings,
        ListingEligibility $eligibility,
        DailyShareRevenue $dailyShareRevenue,
    ): JsonResponse {
        abort_unless(in_array($type, ['apps', 'ads'], true), 404);
        $validated = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:120']]);
        $user = $request->user();
        $activeSubscriptions = $eligibility->activeMarketplaceSubscriptions($user);
        $taskSubscriptions = $activeSubscriptions
            ->filter(fn ($item): bool => $type === 'apps'
                ? ($item->plan?->max_review_tasks ?? 0) > 0
                : ($item->plan?->max_ad_shares ?? 0) > 0)
            ->sortByDesc('starts_at')
            ->values();
        $subscription = $taskSubscriptions->first();
        $plan = $subscription?->plan;
        $isAdSharePackage = $type === 'ads' && $plan?->group?->group_key === 'group_a';
        $dailyLimit = $isAdSharePackage ? null : $taskSubscriptions->sum(fn ($item) => (int) ($item->plan?->daily_task_limit ?? 0));
        $dailyLimit = $dailyLimit > 0 ? $dailyLimit : null;
        $dailyRevenueLimit = $isAdSharePackage ? $plan?->daily_revenue_minor : null;
        $dailyRevenueEarned = $subscription && $dailyRevenueLimit !== null
            ? $dailyShareRevenue->earnedTodayMinor($user, $plan->currency)
            : null;
        $dailyRevenuePending = $subscription && $dailyRevenueLimit !== null
            ? $dailyShareRevenue->pendingMinor($user, $plan->currency)
            : null;
        $dailyRevenueUsed = $dailyRevenueEarned === null || $dailyRevenuePending === null
            ? null
            : $dailyRevenueEarned + $dailyRevenuePending;
        $dailyRevenueBlocked = $dailyRevenueLimit !== null
            && ($dailyRevenueUsed === null || $dailyRevenueUsed >= (int) $dailyRevenueLimit);
        $usedToday = ReviewTask::query()->where('reviewer_id', $user->id)->whereDate('claimed_at', today())->count()
            + AdShareTask::query()->where('user_id', $user->id)->whereDate('claimed_at', today())->count();
        $remainingToday = $dailyLimit === null ? null : max(0, (int) $dailyLimit - $usedToday);
        $packageLimit = $taskSubscriptions->isEmpty() ? 0 : $taskSubscriptions->sum(fn ($item) => (int) ($type === 'apps' ? $item->plan?->max_review_tasks : $item->plan?->max_ad_shares));
        $packageUsed = $taskSubscriptions->isNotEmpty()
            ? ($type === 'apps'
                ? ReviewTask::query()->where('reviewer_id', $user->id)->whereBetween('claimed_at', [$taskSubscriptions->min('starts_at'), $taskSubscriptions->max('ends_at')])->count()
                : AdShareTask::query()->where('user_id', $user->id)->whereBetween('claimed_at', [$taskSubscriptions->min('starts_at'), $taskSubscriptions->max('ends_at')])->count())
            : 0;
        $claimedIds = $type === 'apps'
            ? ReviewTask::query()->where('reviewer_id', $user->id)->pluck('app_review_campaign_id')->all()
            : AdShareTask::query()->where('user_id', $user->id)->pluck('ad_id')->all();

        if (! $subscription || $remainingToday === 0 || $dailyRevenueBlocked || $packageLimit === 0 || ($packageLimit !== null && $packageUsed >= $packageLimit)) {
            $reason = ! $subscription ? 'marketplace_plan_required'
                : ($remainingToday === 0 ? 'daily_limit_reached'
                    : ($dailyRevenueBlocked
                        ? ($dailyRevenueEarned === null ? 'daily_revenue_unavailable' : 'daily_revenue_limit_reached')
                        : ($packageLimit === 0 ? 'package_task_type_not_included' : 'package_limit_reached')));
            return response()->json(['data' => [], 'meta' => [
                'type' => $type,
                'plan' => $plan?->name,
                'daily_limit' => $dailyLimit,
                'used_today' => $usedToday,
                'remaining_today' => $remainingToday,
                'package_limit' => $packageLimit,
                'package_used' => $packageUsed,
                'daily_revenue_limit_minor' => $dailyRevenueLimit,
                'daily_revenue_earned_minor' => $dailyRevenueEarned,
                'daily_revenue_pending_minor' => $dailyRevenuePending,
                'daily_revenue_used_minor' => $dailyRevenueUsed,
                'daily_revenue_currency' => $dailyRevenueLimit === null ? null : $plan?->currency,
                'remaining_daily_revenue_minor' => $dailyRevenueUsed === null || $dailyRevenueLimit === null
                    ? null
                    : max(0, (int) $dailyRevenueLimit - $dailyRevenueUsed),
                'reason' => $reason,
            ]]);
        }

        $page = $type === 'apps'
            ? $listings->apps($user, 50, $validated['search'] ?? null)
            : $listings->ads($user, 50, $validated['search'] ?? null);

        $items = collect($page->items())->filter(function (AppReviewCampaign|Ad $item) use ($type, $user, $eligibility, $claimedIds): bool {
            if (in_array($item->id, $claimedIds)) return false;
            if ($type === 'apps') {
                /** @var AppReviewCampaign $item */
                return $item->app?->status === 'active'
                    && $eligibility->taskAccessStatus($user, $item->location, $item->app?->store, $item->app?->category, 'review') === 'eligible';
            }

            /** @var Ad $item */
            return $eligibility->taskAccessStatus($user, $item->location, taskType: 'ad') === 'eligible';
        })->values();

        $items->each(function (AppReviewCampaign|Ad $item) use ($type): void {
            $item->setAttribute('slots_remaining', $type === 'apps'
                ? ($item->maximum_review === null ? null : max(0, $item->maximum_review - $item->completed_reviews - $item->reserved_reviews))
                : ($item->maximum_share === null ? null : max(0, $item->maximum_share - $item->total_share - $item->reserved_share)));
        });

        return response()->json(['data' => $items, 'meta' => [
            'type' => $type,
            'plan' => $plan?->name,
            'daily_limit' => $dailyLimit,
            'used_today' => $usedToday,
            'remaining_today' => $remainingToday,
            'package_limit' => $packageLimit,
            'package_used' => $packageUsed,
            'daily_revenue_limit_minor' => $dailyRevenueLimit,
            'daily_revenue_earned_minor' => $dailyRevenueEarned,
            'daily_revenue_pending_minor' => $dailyRevenuePending,
            'daily_revenue_used_minor' => $dailyRevenueUsed,
            'daily_revenue_currency' => $dailyRevenueLimit === null ? null : $plan?->currency,
            'remaining_daily_revenue_minor' => $dailyRevenueUsed === null || $dailyRevenueLimit === null
                ? null
                : max(0, (int) $dailyRevenueLimit - $dailyRevenueUsed),
            'total' => $items->count(),
        ]]);
    }
}
