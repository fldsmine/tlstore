<?php

namespace App\Application\Actions\Marketplace;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Collection;

final class ListingEligibility
{
    /** @var array<int, Collection<int, UserSubscription>> */
    private array $subscriptions = [];

    /** Compatibility accessor for screens/actions that need a current package summary. */
    public function activeMarketplaceSubscription(User $user): ?UserSubscription
    {
        return $this->activeMarketplaceSubscriptions($user)->sortByDesc('starts_at')->first();
    }

    /** @return Collection<int, UserSubscription> */
    public function activeMarketplaceSubscriptions(User $user): Collection
    {
        if (! array_key_exists($user->id, $this->subscriptions)) {
            $this->subscriptions[$user->id] = UserSubscription::query()
                ->with(['plan.group'])
                ->where('user_id', $user->id)
                ->where('product_type', 'marketplace_access')
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->get();
        }

        return $this->subscriptions[$user->id];
    }

    public function subscriptionForTask(
        User $user,
        string $taskType,
        ?string $store = null,
        ?string $category = null,
        bool $includeQuestionnaireIncomplete = false,
    ): ?UserSubscription {
        return $this->activeMarketplaceSubscriptions($user)
            ->filter(function (UserSubscription $subscription) use ($user, $taskType, $store, $category, $includeQuestionnaireIncomplete): bool {
                $plan = $subscription->plan;
                if (! $plan) return false;
                if ($taskType === 'review') {
                    if (($plan->max_review_tasks ?? 0) < 1) return false;
                    if ($plan->eligible_app_store && strcasecmp(trim($plan->eligible_app_store), trim((string) $store)) !== 0) return false;
                    if ($plan->eligible_app_category && strcasecmp(trim($plan->eligible_app_category), trim((string) $category)) !== 0) return false;
                } elseif (($plan->max_ad_shares ?? 0) < 1) {
                    return false;
                }

                return $includeQuestionnaireIncomplete || $this->questionnaireComplete($user, $plan->group?->group_key);
            })
            ->sortByDesc('starts_at')
            ->first();
    }

    public function locationAllowed(User $user, ?string $listingLocation): bool
    {
        // Listing location is descriptive metadata; it never restricts task access.
        return true;
    }

    public function canAccess(User $user, ?string $listingLocation): bool
    {
        return $this->activeMarketplaceSubscriptions($user)->isNotEmpty();
    }

    public function appAllowed(User $user, ?string $listingLocation, ?string $store, ?string $category): bool
    {
        return $this->subscriptionForTask($user, 'review', $store, $category) !== null;
    }

    public function taskAccessStatus(
        User $user,
        ?string $listingLocation,
        ?string $store = null,
        ?string $category = null,
        string $taskType = 'ad',
    ): string {
        // $listingLocation is intentionally ignored. Eligibility comes from any
        // active package that covers this task, store, and category.
        $eligible = $this->subscriptionForTask($user, $taskType, $store, $category);
        if ($eligible) return 'eligible';

        $active = $this->activeMarketplaceSubscriptions($user);
        if ($active->isEmpty()) return 'marketplace_plan_required';

        $withoutQuestionnaire = $this->subscriptionForTask($user, $taskType, $store, $category, true);
        if ($withoutQuestionnaire && ! $this->questionnaireComplete($user, $withoutQuestionnaire->plan?->group?->group_key)) {
            return 'subscription_questionnaire_required';
        }

        if ($taskType === 'review') {
            $hasReviewPlan = $active->contains(fn (UserSubscription $subscription): bool => ($subscription->plan?->max_review_tasks ?? 0) > 0);
            return $hasReviewPlan ? 'store_package_required' : 'review_package_required';
        }

        $hasSharePlan = $active->contains(fn (UserSubscription $subscription): bool => ($subscription->plan?->max_ad_shares ?? 0) > 0);
        return $hasSharePlan ? 'subscription_questionnaire_required' : 'share_package_required';
    }

    private function questionnaireComplete(User $user, ?string $groupKey): bool
    {
        if (! in_array($groupKey, ['group_b', 'group_c'], true)) return true;

        return in_array($groupKey, $user->subscription_questionnaire_completed_groups ?? [], true);
    }
}
