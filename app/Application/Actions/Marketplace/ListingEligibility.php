<?php

namespace App\Application\Actions\Marketplace;

use App\Models\Region;
use App\Models\User;
use App\Models\UserLocationEntitlement;
use App\Models\UserSubscription;

final class ListingEligibility
{
    /** @var array<int, UserSubscription|null> */
    private array $subscriptions = [];

    /** @var array<int, list<string>> */
    private array $eligibleLocationCache = [];

    public function activeMarketplaceSubscription(User $user): ?UserSubscription
    {
        if (array_key_exists($user->id, $this->subscriptions)) {
            return $this->subscriptions[$user->id];
        }

        return $this->subscriptions[$user->id] = UserSubscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->where('product_type', 'marketplace_access')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    public function canAccess(User $user, ?string $listingLocation): bool
    {
        $subscription = $this->activeMarketplaceSubscription($user);
        if (! $subscription) {
            return false;
        }

        if ($subscription->plan?->global_listing_access) {
            return true;
        }

        if ($listingLocation === null || $listingLocation === '' || $listingLocation === $user->country_code) {
            return true;
        }

        if ($user->country_code && Region::query()->where('code', $listingLocation)
            ->whereHas('countries', fn ($query) => $query->where('countries.code', $user->country_code))
            ->exists()) {
            return true;
        }

        $entitlements = UserLocationEntitlement::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->get(['target_type', 'target_code']);

        foreach ($entitlements as $entitlement) {
            if ($entitlement->target_code === $listingLocation) {
                return true;
            }

            if ($entitlement->target_type === 'region') {
                $region = Region::query()->where('code', $entitlement->target_code)->with('countries:code')->first();
                if ($region && ($region->countries->contains('code', $listingLocation)
                    || Region::query()->where('code', $listingLocation)
                        ->whereHas('countries', fn ($query) => $query->whereIn('countries.code', $region->countries->pluck('code')))
                        ->exists())) {
                    return true;
                }
            }

            if ($entitlement->target_type === 'country' && Region::query()->where('code', $listingLocation)
                ->whereHas('countries', fn ($query) => $query->where('countries.code', $entitlement->target_code))
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    public function taskAccessStatus(User $user, ?string $listingLocation): string
    {
        if (! $this->activeMarketplaceSubscription($user)) {
            return 'marketplace_plan_required';
        }

        $locations = $this->eligibleLocations($user);
        return ($listingLocation === null || $listingLocation === ''
                || in_array('*', $locations, true)
                || in_array($listingLocation, $locations, true))
            ? 'eligible'
            : 'location_coverage_required';
    }

    /** @return list<string> Locations the profile country and active location entitlements cover. */
    public function eligibleLocations(User $user): array
    {
        if (array_key_exists($user->id, $this->eligibleLocationCache)) {
            return $this->eligibleLocationCache[$user->id];
        }

        $subscription = $this->activeMarketplaceSubscription($user);
        if (! $subscription) {
            return $this->eligibleLocationCache[$user->id] = [];
        }
        if ($subscription->plan?->global_listing_access) {
            return $this->eligibleLocationCache[$user->id] = ['*'];
        }

        $locations = $user->country_code ? [$user->country_code] : [];
        if ($user->country_code) {
            $locations = array_merge($locations, Region::query()->where('is_available', true)->whereHas(
                'countries', fn ($query) => $query->where('countries.code', $user->country_code),
            )->pluck('code')->all());
        }

        $entitlements = UserLocationEntitlement::query()
            ->where('user_id', $user->id)->where('status', 'active')
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->get(['target_type', 'target_code']);

        foreach ($entitlements as $entitlement) {
            $locations[] = $entitlement->target_code;
            if ($entitlement->target_type === 'region') {
                $region = Region::query()->where('code', $entitlement->target_code)->with('countries:code')->first();
                if ($region) {
                    $locations = array_merge($locations, $region->countries->pluck('code')->all());
                    $locations = array_merge($locations, Region::query()->where('is_available', true)->whereHas(
                        'countries', fn ($query) => $query->whereIn('countries.code', $region->countries->pluck('code')),
                    )->pluck('code')->all());
                }
            } elseif ($entitlement->target_type === 'country') {
                $locations = array_merge($locations, Region::query()->where('is_available', true)->whereHas(
                    'countries', fn ($query) => $query->where('countries.code', $entitlement->target_code),
                )->pluck('code')->all());
            }
        }

        return $this->eligibleLocationCache[$user->id] = array_values(array_unique($locations));
    }
}
