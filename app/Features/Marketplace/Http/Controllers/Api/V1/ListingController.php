<?php

namespace App\Features\Marketplace\Http\Controllers\Api\V1;

use App\Application\Actions\Marketplace\ListEligibleListings;
use App\Application\Actions\Marketplace\ListingEligibility;
use App\Models\Ad;
use App\Models\AppReviewCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListingController
{
    public function apps(Request $request, ListEligibleListings $listings, ListingEligibility $eligibility): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'featured' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'in:latest,reward_desc'],
        ]);
        $result = $listings->apps($request->user(), $validated['per_page'] ?? 20, $validated['search'] ?? null, (bool) ($validated['featured'] ?? false), $validated['sort'] ?? 'latest');
        $result->getCollection()->each(fn (AppReviewCampaign $campaign) => $campaign->setAttribute(
            'task_access_status', $eligibility->taskAccessStatus($request->user(), $campaign->location, $campaign->app?->store, $campaign->app?->category, 'review'),
        ));

        return response()->json(['data' => $result->items(), 'meta' => [
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'total' => $result->total(),
            'last_page' => $result->lastPage(),
        ]]);
    }

    public function ads(Request $request, ListEligibleListings $listings, ListingEligibility $eligibility): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'featured' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'in:latest,reward_desc'],
        ]);
        $result = $listings->ads($request->user(), $validated['per_page'] ?? 20, $validated['search'] ?? null, (bool) ($validated['featured'] ?? false), $validated['sort'] ?? 'latest');
        $result->getCollection()->each(fn (Ad $ad) => $ad->setAttribute(
            'task_access_status', $eligibility->taskAccessStatus($request->user(), $ad->location, taskType: 'ad'),
        ));

        return response()->json(['data' => $result->items(), 'meta' => [
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'total' => $result->total(),
            'last_page' => $result->lastPage(),
        ]]);
    }

    public function app(Request $request, AppReviewCampaign $campaign, ListEligibleListings $listings, ListingEligibility $eligibility): JsonResponse
    {
        $item = $listings->appDetail($request->user(), $campaign);
        $item->setAttribute('task_access_status', $eligibility->taskAccessStatus($request->user(), $item->location, $item->app?->store, $item->app?->category, 'review'));

        return response()->json(['data' => $item]);
    }

    public function ad(Request $request, Ad $ad, ListEligibleListings $listings, ListingEligibility $eligibility): JsonResponse
    {
        $item = $listings->adDetail($request->user(), $ad);
        $item->setAttribute('task_access_status', $eligibility->taskAccessStatus($request->user(), $item->location, taskType: 'ad'));

        return response()->json(['data' => $item]);
    }
}
