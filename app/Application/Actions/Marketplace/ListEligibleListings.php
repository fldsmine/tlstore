<?php

namespace App\Application\Actions\Marketplace;

use App\Models\Ad;
use App\Models\AppReviewCampaign;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListEligibleListings
{
    /** @return LengthAwarePaginator<AppReviewCampaign> */
    public function apps(User $user, int $perPage = 20, ?string $search = null, ?string $location = null, bool $featured = false, string $sort = 'latest'): LengthAwarePaginator
    {
        $query = AppReviewCampaign::query()
            ->with(['app.screenshots', 'app.storeListingSnapshots'])
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('maximum_review')->orWhereRaw('completed_reviews + reserved_reviews < maximum_review'))
            ->when($search !== null && trim($search) !== '', fn (Builder $query) => $query->whereHas('app', fn (Builder $app) => $app->where(function (Builder $builder) use ($search): void {
                $term = '%'.trim($search).'%';
                $builder->where('name', 'like', $term)->orWhere('developer', 'like', $term)
                    ->orWhere('store', 'like', $term)->orWhere('category', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })))
            ->when($location !== null, fn (Builder $query) => $location === 'global'
                ? $query->whereNull('location')
                : $query->where('location', strtoupper($location)))
            ->when($featured, fn (Builder $query) => $query->whereHas('app', fn (Builder $app) => $app->where('featured', true)))
            ->when($sort === 'reward_desc', fn (Builder $query) => $query->orderByDesc('review_reward_minor')->orderByDesc('id'), fn (Builder $query) => $query->latest('id'));
        return $query->paginate(min(max($perPage, 1), 50));
    }

    /** @return \Illuminate\Support\Collection<int, Ad> */
    public function featuredAds(int $limit = 5)
    {
        return Ad::query()
            ->where('featured', true)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('maximum_share')->orWhereRaw('total_share + reserved_share < maximum_share'))
            ->latest('id')
            ->limit(min(max($limit, 1), 20))
            ->get();
    }

    /** @return LengthAwarePaginator<AppReviewCampaign> */
    public function app_sport(int $perPage = 5): LengthAwarePaginator
    {
        $query = AppReviewCampaign::query()
            ->with(['app.screenshots', 'app.storeListingSnapshots'])
            ->whereHas('app', fn (Builder $app) => $app->where('featured', true))
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('maximum_review')->orWhereRaw('completed_reviews + reserved_reviews < maximum_review'))
            ->latest('id');

        return $query->paginate(min(max($perPage, 1), 50));
    }
	
    /** @return LengthAwarePaginator<Ad> */
    public function ads(User $user, int $perPage = 20, ?string $search = null, ?string $location = null, bool $featured = false, string $sort = 'latest'): LengthAwarePaginator
    {
        $query = Ad::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('maximum_share')->orWhereRaw('total_share + reserved_share < maximum_share'))
            ->when($search !== null && trim($search) !== '', fn (Builder $query) => $query->where(fn (Builder $builder) => $builder
                ->where('title', 'like', '%'.trim($search).'%')
                ->orWhere('description', 'like', '%'.trim($search).'%')
                ->orWhere('share_message', 'like', '%'.trim($search).'%')))
            ->when($location !== null, fn (Builder $query) => $location === 'global'
                ? $query->whereNull('location')
                : $query->where('location', strtoupper($location)))
            ->when($featured, fn (Builder $query) => $query->where('featured', true))
            ->when($sort === 'reward_desc', fn (Builder $query) => $query->orderByDesc('share_reward')->orderByDesc('id'), fn (Builder $query) => $query->latest('id'));
        return $query->paginate(min(max($perPage, 1), 50));
    }

    public function appDetail(User $user, AppReviewCampaign $campaign): AppReviewCampaign
    {
        $query = AppReviewCampaign::query()->with(['app.screenshots', 'app.storeListingSnapshots'])
            ->whereKey($campaign->id)->where('status', 'active')
            ->where(fn ($builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($builder) => $builder->whereNull('maximum_review')->orWhereRaw('completed_reviews + reserved_reviews < maximum_review'));
        return $query->firstOrFail();
    }

    public function adDetail(User $user, Ad $ad): Ad
    {
        $query = Ad::query()->whereKey($ad->id)->where('status', 'active')
            ->where(fn ($builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($builder) => $builder->whereNull('maximum_share')->orWhereRaw('total_share + reserved_share < maximum_share'));
        return $query->firstOrFail();
    }
}
