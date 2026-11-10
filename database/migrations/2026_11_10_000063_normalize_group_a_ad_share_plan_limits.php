<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groupId = DB::table('subscription_groups')->where('group_key', 'group_a')->value('id');
        if ($groupId === null) return;

        $plans = DB::table('subscription_plans')
            ->where('subscription_group_id', $groupId)
            ->where('product_type', 'marketplace_access')
            ->get(['id', 'features']);
        if ($plans->isEmpty()) return;

        foreach ($plans as $plan) {
            $features = json_decode((string) $plan->features, true);
            if (! is_array($features)) $features = [];
            unset($features['task_reward'], $features['task_reward_minor'], $features['daily_task_limit']);

            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'task_reward_minor' => null,
                'daily_task_limit' => null,
                'features' => json_encode($features, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }

        DB::table('subscription_plan_features')
            ->whereIn('subscription_plan_id', $plans->pluck('id'))
            ->whereIn('feature_key', ['task_reward', 'task_reward_minor', 'daily_task_limit'])
            ->delete();
    }

    public function down(): void
    {
        // The old Group A fields were package misconfiguration, not recoverable history.
    }
};
