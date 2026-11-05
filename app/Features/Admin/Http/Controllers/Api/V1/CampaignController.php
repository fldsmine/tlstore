<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\Ad;
use App\Models\Admin;
use App\Models\AppReviewCampaign;
use App\Models\App as StoreApp;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

final class CampaignController
{
    public function reviewCampaigns(): JsonResponse
    {
        return response()->json(['data' => AppReviewCampaign::query()->with('app')->latest()->paginate(30)]);
    }

    public function createReviewCampaign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_id' => ['required', 'integer', 'exists:apps,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'paid_amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'location' => ['nullable', 'string', 'max:32'],
            'review_reward_minor' => ['required', 'integer', 'min:1'],
            'maximum_review' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['sometimes', 'in:draft,active,paused'],
        ]);
        $data['location'] = $this->normalizeLocation($data['location'] ?? null);
        if ($data['paid_amount_minor'] < $data['review_reward_minor'] * $data['maximum_review']) {
            return response()->json(['message' => 'The reward pool must cover the configured review maximum.'], 422);
        }

        $campaign = AppReviewCampaign::query()->create([
            ...$data,
            'campaign_key' => 'admin-review-'.Str::lower(Str::random(20)),
            'status' => $data['status'] ?? 'draft',
        ]);
        StoreApp::query()->whereKey($campaign->app_id)->update([
            'paid_amount' => CurrencyMinorUnits::fromMinor($campaign->paid_amount_minor, $campaign->currency),
            'location' => $campaign->location,
            'currency' => $campaign->currency,
            'review_reward' => CurrencyMinorUnits::fromMinor($campaign->review_reward_minor, $campaign->currency),
            'maximum_review' => $campaign->maximum_review,
        ]);
        $this->audit($request->user(), 'app_review_campaign.created', 'app_review_campaign', (string) $campaign->id, null, $campaign->only(['status', 'paid_amount_minor', 'maximum_review', 'location']));
        Cache::forget('api:login-spot:v3');

        return response()->json(['data' => $campaign->load('app')], 201);
    }

    public function updateReviewCampaign(Request $request, AppReviewCampaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'owner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'paid_amount_minor' => ['sometimes', 'integer', 'min:1'], 'currency' => ['sometimes', 'string', 'size:3'],
            'location' => ['sometimes', 'nullable', 'string', 'max:32'],
            'review_reward_minor' => ['sometimes', 'integer', 'min:1'], 'maximum_review' => ['sometimes', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'], 'ends_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:draft,active,paused,ended,archived'],
        ]);
        if (array_key_exists('location', $data)) $data['location'] = $this->normalizeLocation($data['location']);
        $pool = $data['paid_amount_minor'] ?? $campaign->paid_amount_minor;
        $reward = $data['review_reward_minor'] ?? $campaign->review_reward_minor;
        $maximum = $data['maximum_review'] ?? $campaign->maximum_review;
        if ($maximum && $pool < $reward * $maximum) {
            return response()->json(['message' => 'The reward pool must cover the configured review maximum.'], 422);
        }
        if ($maximum && $maximum < $campaign->completed_reviews + $campaign->reserved_reviews) {
            return response()->json(['message' => 'The review maximum cannot be lower than completed and reserved reviews.'], 422);
        }
        if (($campaign->completed_reviews + $campaign->reserved_reviews) > 0
            && (isset($data['currency']) || isset($data['review_reward_minor']))) {
            return response()->json(['message' => 'Campaign currency and reward cannot change after review slots have been claimed.'], 422);
        }
        $before = $campaign->only(array_keys($data));
        $campaign->fill($data)->save();
        StoreApp::query()->whereKey($campaign->app_id)->update([
            'paid_amount' => CurrencyMinorUnits::fromMinor($campaign->paid_amount_minor, $campaign->currency),
            'location' => $campaign->location,
            'currency' => $campaign->currency,
            'review_reward' => CurrencyMinorUnits::fromMinor($campaign->review_reward_minor, $campaign->currency),
            'maximum_review' => $campaign->maximum_review,
        ]);
        $this->audit($request->user(), 'app_review_campaign.updated', 'app_review_campaign', (string) $campaign->id, $before, $campaign->only(array_keys($data)));
        Cache::forget('api:login-spot:v3');

        return response()->json(['data' => $campaign->refresh()->load('app')]);
    }

    public function ads(): JsonResponse
    {
        return response()->json(['data' => Ad::query()->latest()->paginate(30)]);
    }

    public function createAd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string'],
            'share_message' => ['nullable', 'string'], 'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'creative_path' => ['nullable', 'string', 'max:255'], 'destination_url' => ['nullable', 'url', 'max:2048'],
            'paid_amount' => ['required', 'numeric', 'gt:0'], 'location' => ['nullable', 'string', 'max:32'],
            'currency' => ['required', 'string', 'size:3'], 'share_reward' => ['required', 'numeric', 'gt:0'],
            'maximum_share' => ['required', 'integer', 'min:1'], 'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'], 'status' => ['sometimes', 'in:draft,active,paused'],
            'featured' => ['sometimes', 'boolean'],
        ]);
        $data['location'] = $this->normalizeLocation($data['location'] ?? null);
        if ((float) $data['paid_amount'] + 0.000001 < (float) $data['share_reward'] * $data['maximum_share']) {
            return response()->json(['message' => 'The reward pool must cover the configured share maximum.'], 422);
        }

        $ad = Ad::query()->create([
            ...$data,
            'campaign_key' => 'admin-ad-'.Str::lower(Str::random(20)),
            'status' => $data['status'] ?? 'draft',
        ]);
        $this->audit($request->user(), 'ad_campaign.created', 'ad', (string) $ad->id, null, $ad->only(['status', 'paid_amount', 'maximum_share', 'location']));
        Cache::forget('api:login-spot:v3');

        return response()->json(['data' => $ad], 201);
    }

    public function updateAd(Request $request, Ad $ad): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'], 'description' => ['sometimes', 'nullable', 'string'],
            'share_message' => ['sometimes', 'nullable', 'string'], 'owner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'creative_path' => ['sometimes', 'nullable', 'string', 'max:255'], 'destination_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'paid_amount' => ['sometimes', 'numeric', 'gt:0'], 'location' => ['sometimes', 'nullable', 'string', 'max:32'],
            'currency' => ['sometimes', 'string', 'size:3'], 'share_reward' => ['sometimes', 'numeric', 'gt:0'],
            'maximum_share' => ['sometimes', 'integer', 'min:1'], 'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'], 'status' => ['sometimes', 'in:draft,active,paused,ended,archived'],
            'featured' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('location', $data)) $data['location'] = $this->normalizeLocation($data['location']);
        $pool = (float) ($data['paid_amount'] ?? $ad->paid_amount);
        $reward = (float) ($data['share_reward'] ?? $ad->share_reward);
        $maximum = (int) ($data['maximum_share'] ?? $ad->maximum_share);
        if ($maximum && $pool + 0.000001 < $reward * $maximum) {
            return response()->json(['message' => 'The reward pool must cover the configured share maximum.'], 422);
        }
        if ($maximum && $maximum < (int) $ad->total_share + (int) $ad->reserved_share) {
            return response()->json(['message' => 'The share maximum cannot be lower than completed and reserved shares.'], 422);
        }
        if (((int) $ad->total_share + (int) $ad->reserved_share) > 0
            && (isset($data['currency']) || isset($data['share_reward']))) {
            return response()->json(['message' => 'Ad currency and reward cannot change after share slots have been claimed.'], 422);
        }
        $before = $ad->only(array_keys($data));
        $ad->fill($data)->save();
        $this->audit($request->user(), 'ad_campaign.updated', 'ad', (string) $ad->id, $before, $ad->only(array_keys($data)));
        Cache::forget('api:login-spot:v3');

        return response()->json(['data' => $ad->refresh()]);
    }

    private function normalizeLocation(?string $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $countryCode = strtoupper($location);
        if (Country::query()->where('code', $countryCode)->exists()) {
            return $countryCode;
        }
        if (! Region::query()->where('code', $location)->exists()) {
            abort(422, 'Location must be an available country code, region code, or null for global.');
        }

        return $location;
    }

    private function audit(Admin $admin, string $action, string $type, string $id, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'before_values' => $before, 'after_values' => $after, 'created_at' => now(),
        ]);
    }
}
