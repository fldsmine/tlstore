<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Admin;
use App\Models\PackageAddon;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PackageAddonController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PackageAddon::query()
            ->with(['plan.group'])
            ->withCount([
                'purchases as total_users_count',
                'purchases as active_users_count' => fn ($query) => $query
                    ->where('status', 'active')
                    ->where(fn ($active) => $active->whereNull('ends_at')->orWhere('ends_at', '>', now())),
            ])
            ->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id', 'unique:package_addons,subscription_plan_id'],
            'addon_key' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:package_addons,addon_key'],
            'name' => ['required', 'string', 'max:120'],
            'features' => ['required', 'array'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', 'in:USD'],
            'billing_interval' => ['sometimes', 'in:weekly,monthly,quarterly,yearly'],
            'duration_count' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);
        abort_unless($plan->product_type->value === 'marketplace_access', 422, 'Add-ons can only be attached to marketplace review packages.');
        $plan->update(['addons' => true]);
        $addon = PackageAddon::query()->create($data + ['billing_interval' => 'monthly', 'duration_count' => 1, 'is_active' => true]);
        $admin = $request->user();
        if ($admin instanceof Admin) {
            AuditLog::query()->create([
                'actor_admin_id' => $admin->id, 'action' => 'package_addon.created',
                'entity_type' => 'package_addon', 'entity_id' => (string) $addon->id,
                'before_values' => null, 'after_values' => $addon->toArray(), 'created_at' => now(),
            ]);
        }
        return response()->json(['data' => $addon->load('plan.group')], 201);
    }

    public function update(Request $request, PackageAddon $addon): JsonResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['sometimes', 'required', 'integer', Rule::exists('subscription_plans', 'id'), Rule::unique('package_addons', 'subscription_plan_id')->ignore($addon->id)],
            'addon_key' => ['sometimes', 'required', 'string', 'max:64', 'alpha_dash', Rule::unique('package_addons', 'addon_key')->ignore($addon->id)],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'discount' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'currency' => ['sometimes', 'required', 'in:USD'],
            'features' => ['sometimes', 'required', 'array'],
            'billing_interval' => ['sometimes', 'required', 'in:weekly,monthly,quarterly,yearly'],
            'duration_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:120'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);
        $nextPlan = null;
        if (isset($data['subscription_plan_id'])) {
            $nextPlan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);
            abort_unless($nextPlan->product_type->value === 'marketplace_access', 422, 'Add-ons can only be attached to marketplace review packages.');
            if ($nextPlan->id !== $addon->subscription_plan_id && $addon->purchases()->exists()) {
                abort(409, 'The subscription plan cannot be changed after add-on purchases have been recorded.');
            }
        }
        $before = $addon->only(array_keys($data));
        $addon->fill($data)->save();
        $addon->plan()->update(['addons' => true]);
        if ($nextPlan && $nextPlan->id !== (int) ($before['subscription_plan_id'] ?? $addon->subscription_plan_id)) {
            SubscriptionPlan::query()->whereKey($before['subscription_plan_id'] ?? null)->update(['addons' => false]);
            $nextPlan->update(['addons' => true]);
        }
        $admin = $request->user();
        if ($admin instanceof Admin) {
            AuditLog::query()->create([
                'actor_admin_id' => $admin->id, 'action' => 'package_addon.updated',
                'entity_type' => 'package_addon', 'entity_id' => (string) $addon->id,
                'before_values' => $before, 'after_values' => $addon->only(array_keys($data)), 'created_at' => now(),
            ]);
        }

        return response()->json(['data' => $addon->refresh()->load('plan.group')]);
    }

    public function users(Request $request, PackageAddon $addon): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'in:active,pending,expired,cancelled'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $addon->purchases()->with([
            'user:id,full_name,username,email',
            'paymentIntent:id,reference,status,provider',
        ])->latest();
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($records) => $records
                ->whereHas('user', fn ($users) => $users->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('paymentIntent', fn ($payments) => $payments->where('provider_reference', 'like', "%{$search}%")));
        }

        return response()->json(['data' => $query->paginate($filters['per_page'] ?? 20)]);
    }

    public function destroy(Request $request, PackageAddon $addon): JsonResponse
    {
        abort_if($addon->purchases()->exists(), 409, 'This add-on has purchase records. Disable it to prevent new purchases while preserving its history.');
        $before = $addon->toArray();
        $planId = $addon->subscription_plan_id;
        $addon->delete();
        SubscriptionPlan::query()->whereKey($planId)->update(['addons' => false]);
        $admin = $request->user();
        if ($admin instanceof Admin) {
            AuditLog::query()->create([
                'actor_admin_id' => $admin->id, 'action' => 'package_addon.deleted',
                'entity_type' => 'package_addon', 'entity_id' => (string) $addon->id,
                'before_values' => $before, 'after_values' => null, 'created_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Package add-on deleted.']);
    }
}
