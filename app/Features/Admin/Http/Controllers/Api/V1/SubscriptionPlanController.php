<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Region;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubscriptionPlanController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::query()->with(['features', 'locationCoverage'])->orderBy('product_type')->orderBy('price_minor')->paginate(50)]);
    }

    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'tier_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'billing_interval' => ['sometimes', 'in:weekly,monthly,quarterly'],
            'duration_count' => ['sometimes', 'integer', 'min:1', 'max:36'],
            'price_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'discount' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'bonus' => ['sometimes', 'numeric', 'min:0'],
            'subscription_referral_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'task_referral_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'max_review_tasks' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_ad_shares' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'global_listing_access' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_special' => ['sometimes', 'boolean'],
        ]);
        $before = $plan->only(array_keys($data));
        $plan->fill($data)->save();
        $this->audit($request->user(), 'subscription_plan.updated', 'subscription_plan', (string) $plan->id, $before, $plan->only(array_keys($data)));

        return response()->json(['data' => $plan->refresh()->load(['features', 'locationCoverage'])]);
    }

    public function syncCoverage(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        abort_unless($plan->product_type->value === 'location_coverage', 422);
        $data = $request->validate([
            'region_codes' => ['required', 'array'],
            'region_codes.*' => ['required', 'string', 'exists:regions,code', 'distinct'],
        ]);

        DB::transaction(function () use ($request, $plan, $data): void {
            $before = $plan->locationCoverage()->get(['target_type', 'target_code'])->toArray();
            $plan->locationCoverage()->delete();
            foreach ($data['region_codes'] as $code) {
                Region::query()->where('code', $code)->where('is_available', true)->firstOrFail();
                $plan->locationCoverage()->create(['target_type' => 'region', 'target_code' => $code]);
            }
            $this->audit($request->user(), 'location_plan.coverage_updated', 'subscription_plan', (string) $plan->id, $before, $data);
        });

        return response()->json(['data' => $plan->refresh()->load('locationCoverage')]);
    }

    private function audit(Admin $admin, string $action, string $type, string $id, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'before_values' => $before, 'after_values' => $after, 'created_at' => now(),
        ]);
    }
}
