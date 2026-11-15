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
use Illuminate\Support\Facades\Storage;

final class CampaignController
{
    public function reviewCampaigns(): JsonResponse
    {
        return response()->json(['data' => AppReviewCampaign::query()->with(['app', 'reviewTemplateVersion.template', 'reviewTemplateVersion.questions'])->latest()->paginate(30)]);
    }

    public function createReviewCampaign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_id' => ['required', 'integer', 'exists:apps,id'],
            'review_template_version_id' => ['nullable', 'integer', 'exists:review_template_versions,id'],
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
        if (! empty($data['review_template_version_id'])
            && ! \App\Models\ReviewTemplateVersion::query()->whereKey($data['review_template_version_id'])->where('status', 'published')->exists()) {
            return response()->json(['message' => 'Choose a published review-question template version.'], 422);
        }
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
            'review_template_version_id' => ['sometimes', 'nullable', 'integer', 'exists:review_template_versions,id'],
            'paid_amount_minor' => ['sometimes', 'integer', 'min:1'], 'currency' => ['sometimes', 'string', 'size:3'],
            'location' => ['sometimes', 'nullable', 'string', 'max:32'],
            'review_reward_minor' => ['sometimes', 'integer', 'min:1'], 'maximum_review' => ['sometimes', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'], 'ends_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:draft,active,paused,ended,archived'],
        ]);
        if (array_key_exists('location', $data)) $data['location'] = $this->normalizeLocation($data['location']);
        if (! empty($data['review_template_version_id'])
            && ! \App\Models\ReviewTemplateVersion::query()->whereKey($data['review_template_version_id'])->where('status', 'published')->exists()) {
            return response()->json(['message' => 'Choose a published review-question template version.'], 422);
        }
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
            && (($data['currency'] ?? $campaign->currency) !== $campaign->currency
                || (int) ($data['review_reward_minor'] ?? $campaign->review_reward_minor) !== (int) $campaign->review_reward_minor)) {
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
        return response()->json(['data' => Ad::query()->with('owner:id,full_name,username,email')->latest()->paginate(100)]);
    }

    public function createAd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'type' => ['required', 'string', 'max:48'], 'description' => ['nullable', 'string'],
            'share_message' => ['nullable', 'string'], 'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'creative_path' => ['nullable', 'string', 'max:255'], 'creative_file' => ['nullable', 'image', 'max:10240'],
            'destination_url' => ['nullable', 'url', 'max:2048'],
            'paid_amount' => ['required', 'numeric', 'gt:0'], 'location' => ['nullable', 'string', 'max:32'],
            'currency' => ['required', 'string', 'size:3'], 'share_reward' => ['required', 'numeric', 'gt:0'],
            'maximum_share' => ['required', 'integer', 'min:1'], 'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'], 'status' => ['sometimes', 'in:draft,pending,active,paused,rejected'],
            'featured' => ['sometimes', 'boolean'],
        ]);
        $data['location'] = $this->normalizeLocation($data['location'] ?? null);
        if ((float) $data['paid_amount'] + 0.000001 < (float) $data['share_reward'] * $data['maximum_share']) {
            return response()->json(['message' => 'The reward pool must cover the configured share maximum.'], 422);
        }
        if (isset($data['creative_file'])) {
            $data['creative_path'] = $data['creative_file']->store('ads/creatives', 'public');
            unset($data['creative_file']);
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
            'type' => ['sometimes', 'string', 'max:48'],
            'share_message' => ['sometimes', 'nullable', 'string'], 'owner_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'creative_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'creative_file' => ['sometimes', 'image', 'max:10240'],
            'destination_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'paid_amount' => ['sometimes', 'numeric', 'gt:0'], 'location' => ['sometimes', 'nullable', 'string', 'max:32'],
            'currency' => ['sometimes', 'string', 'size:3'], 'share_reward' => ['sometimes', 'numeric', 'gt:0'],
            'maximum_share' => ['sometimes', 'integer', 'min:1'], 'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'], 'status' => ['sometimes', 'in:draft,pending,active,paused,rejected,ended,archived'],
            'featured' => ['sometimes', 'boolean'],
        ]);
        $oldCreative = $ad->getRawOriginal('creative_path');
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
            && (($data['currency'] ?? $ad->currency) !== $ad->currency
                || (isset($data['share_reward']) && (float) $data['share_reward'] !== (float) $ad->share_reward))) {
            return response()->json(['message' => 'Ad currency and reward cannot change after share slots have been claimed.'], 422);
        }
        $creativeToDelete = null;
        if (isset($data['creative_file'])) {
            $data['creative_path'] = $data['creative_file']->store('ads/creatives', 'public');
            unset($data['creative_file']);
            if ($oldCreative && ! filter_var($oldCreative, FILTER_VALIDATE_URL)) {
                $creativeToDelete = $oldCreative;
            }
        } elseif (array_key_exists('creative_path', $data) && $data['creative_path'] === null
            && $oldCreative && ! filter_var($oldCreative, FILTER_VALIDATE_URL)) {
            $creativeToDelete = $oldCreative;
        } elseif (array_key_exists('creative_path', $data) && $data['creative_path'] !== $oldCreative
            && $oldCreative && ! filter_var($oldCreative, FILTER_VALIDATE_URL)) {
            $creativeToDelete = $oldCreative;
        }
        $before = $ad->only(array_keys($data));
        $ad->fill($data)->save();
        if ($creativeToDelete !== null) {
            Storage::disk('public')->delete($creativeToDelete);
        }
        $this->audit($request->user(), 'ad_campaign.updated', 'ad', (string) $ad->id, $before, $ad->only(array_keys($data)));
        Cache::forget('api:login-spot:v3');

        return response()->json(['data' => $ad->refresh()]);
    }

    public function approveAd(Request $request, Ad $ad): JsonResponse
    {
        abort_if($ad->status->value === 'archived', 409, 'Archived ads cannot be approved.');
        $before = ['status' => $ad->status->value];
        $ad->update(['status' => 'active']);
        $this->audit($request->user(), 'ad_campaign.approved', 'ad', (string) $ad->id, $before, ['status' => 'active']);
        Cache::forget('api:login-spot:v3');

        return response()->json(['data' => $ad->refresh(), 'message' => 'Ad approved and activated.']);
    }

    public function destroyAd(Request $request, Ad $ad): JsonResponse
    {
        $before = $ad->only(['title', 'type', 'status', 'total_share', 'reserved_share']);
        $ad->delete();
        $this->audit($request->user(), 'ad_campaign.deleted', 'ad', (string) $ad->id, $before, ['deleted_at' => now()->toISOString()]);
        Cache::forget('api:login-spot:v3');

        return response()->json(['message' => 'Ad listing deleted.']);
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
