<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubscriptionPlanController
{
    public function groups(): JsonResponse
    {
        return response()->json(['data' => SubscriptionGroup::query()->with(['plans' => fn ($query) => $query->where('is_active', true)->orderBy('price_minor')])->orderBy('sort_order')->get()]);
    }

    public function updateGroup(Request $request, SubscriptionGroup $group): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $before = $group->only(array_keys($data));
        $group->fill($data)->save();
        $this->audit($request->user(), 'subscription_group.updated', 'subscription_group', (string) $group->id, $before, $group->only(array_keys($data)));

        return response()->json(['data' => $group->refresh()->load('plans')]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::query()->where('product_type', 'marketplace_access')->with(['features', 'group'])->orderBy('subscription_group_id')->orderBy('price_minor')->paginate(50)]);
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
            'subscription_group_id' => ['sometimes', 'exists:subscription_groups,id'],
            'eligible_app_store' => ['sometimes', 'nullable', 'string', 'max:100'],
            'eligible_app_category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'minimum_withdrawal_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'withdrawal_days' => ['sometimes', 'nullable', 'array'],
            'withdrawal_days.*' => ['string', 'max:32'],
            'daily_revenue_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'task_reward_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'daily_task_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_special' => ['sometimes', 'boolean'],
        ]);
        if ($plan->product_type->value !== 'marketplace_access') {
            return response()->json(['message' => 'Location coverage packages have been retired.'], 422);
        }
        $groupId = $data['subscription_group_id'] ?? $plan->subscription_group_id;
        $isAdShareGroup = $groupId !== null
            && SubscriptionGroup::query()->whereKey($groupId)->value('group_key') === 'group_a';
        if ($isAdShareGroup) {
            // Group A earnings come from each ad's share_reward and the plan's
            // daily revenue cap, so these review-package fields do not apply.
            $data['task_reward_minor'] = null;
            $data['daily_task_limit'] = null;
            if (isset($data['features']) && is_array($data['features'])) {
                unset($data['features']['task_reward'], $data['features']['daily_task_limit']);
            }
        }
        $before = $plan->only(array_keys($data));
        DB::transaction(function () use ($plan, $data, $isAdShareGroup): void {
            $plan->fill($data)->save();
            if (array_key_exists('features', $data)) {
                $plan->features()->delete();
                foreach ($data['features'] as $key => $value) {
                    $featureKey = is_string($key) ? $key : 'feature_'.($key + 1);
                    $plan->features()->create([
                        'feature_key' => $featureKey,
                        'label' => ucwords(str_replace('_', ' ', $featureKey)),
                        'value' => $value,
                    ]);
                }
            }
            if ($isAdShareGroup) {
                $plan->features()->whereIn('feature_key', ['task_reward', 'daily_task_limit'])->delete();
            }
        });
        $this->audit($request->user(), 'subscription_plan.updated', 'subscription_plan', (string) $plan->id, $before, $plan->only(array_keys($data)));

        return response()->json(['data' => $plan->refresh()->load(['features', 'group'])]);
    }

    private function audit(Admin $admin, string $action, string $type, string $id, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'before_values' => $before, 'after_values' => $after, 'created_at' => now(),
        ]);
    }
}
