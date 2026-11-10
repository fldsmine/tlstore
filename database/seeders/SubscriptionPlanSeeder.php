<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Keep this seeder self-contained for targeted demo-data runs.
        $this->call(SubscriptionGroupSeeder::class);

        $groups = DB::table('subscription_groups')->pluck('id', 'group_key');
        $now = now();

        $plans = [
            [
                'plan_key' => 'demo-market-lite', 'subscription_group_id' => $groups['group_a'],
                'product_type' => 'marketplace_access', 'name' => 'Lite', 'tier_key' => 'lite',
                'billing_interval' => 'monthly', 'duration_count' => 1, 'price_minor' => 150000, 'currency' => 'NGN',
                'discount' => 0, 'bonus' => 500, 'subscription_referral_percent' => 50, 'task_referral_percent' => 0,
                'is_special' => 0, 'max_review_tasks' => 0, 'max_ad_shares' => 1000,
                'eligible_app_store' => null, 'eligible_app_category' => null,
                'minimum_withdrawal_minor' => 600000, 'withdrawal_days' => ['Monthly 28th'],
                'daily_revenue_minor' => 15000, 'task_reward_minor' => null, 'daily_task_limit' => null,
                'features' => [
                    'referral_bonus' => 750,
                    'minimum_withdrawal' => 6000,
                    'withdrawal_days' => ['Monthly'],
                    'welcome_bonus' => 500,
                ],
            ],
            [
                'plan_key' => 'demo-market-premium', 'subscription_group_id' => $groups['group_a'],
                'product_type' => 'marketplace_access', 'name' => 'Premium', 'tier_key' => 'premium',
                'billing_interval' => 'monthly', 'duration_count' => 1, 'price_minor' => 350000, 'currency' => 'NGN',
                'discount' => 0, 'bonus' => 0, 'subscription_referral_percent' => 50, 'task_referral_percent' => 0,
                'is_special' => 1, 'max_review_tasks' => 0, 'max_ad_shares' => 120,
                'eligible_app_store' => null, 'eligible_app_category' => null,
                'minimum_withdrawal_minor' => 1150000, 'withdrawal_days' => ['Monthly 28th'],
                'daily_revenue_minor' => 30000, 'task_reward_minor' => null, 'daily_task_limit' => null,
                'features' => [
                    'referral_bonus' => 1750,
                    'minimum_withdrawal' => 11500,
                    'withdrawal_days' => ['Monthly'],
                ],
            ],
            [
                'plan_key' => 'demo-market-gold', 'subscription_group_id' => $groups['group_a'],
                'product_type' => 'marketplace_access', 'name' => 'Gold', 'tier_key' => 'gold',
                'billing_interval' => 'monthly', 'duration_count' => 1, 'price_minor' => 620000, 'currency' => 'NGN',
                'discount' => 12, 'bonus' => 0, 'subscription_referral_percent' => 54.6, 'task_referral_percent' => 0,
                'is_special' => 1, 'max_review_tasks' => 0, 'max_ad_shares' => 140,
                'eligible_app_store' => null, 'eligible_app_category' => null,
                'minimum_withdrawal_minor' => 1850000, 'withdrawal_days' => ['Monthly 28th'],
                'daily_revenue_minor' => 45000, 'task_reward_minor' => null, 'daily_task_limit' => null,
                'features' => [
                    'referral_bonus' => 3000,
                    'minimum_withdrawal' => 18500,
                    'withdrawal_days' => ['Monthly'],
                    'package_percent_discount' => 12,
                ],
            ],
            [
                'plan_key' => 'review-google-play-4m', 'subscription_group_id' => $groups['group_b'],
                'product_type' => 'marketplace_access', 'name' => 'Playstore review', 'tier_key' => 'playstore_review',
                'billing_interval' => 'monthly', 'duration_count' => 4, 'price_minor' => 1215000, 'currency' => 'NGN',
                'discount' => 0, 'bonus' => 0, 'subscription_referral_percent' => 0, 'task_referral_percent' => 6,
                'is_special' => 0, 'max_review_tasks' => 360, 'max_ad_shares' => 0,
                'eligible_app_store' => 'Google Play', 'eligible_app_category' => null,
                'minimum_withdrawal_minor' => 300000, 'withdrawal_days' => ['Friday'],
                'daily_revenue_minor' => 63000, 'task_reward_minor' => 21000, 'daily_task_limit' => 3,
                'features' => [
                    'amount' => 12150,
                    'minimum_withdrawal' => 3000,
                    'withdrawal_days' => ['Friday'],
                    'daily_revenue' => 630,
                    'per_survey' => 210,
                    'daily_task_limit' => 3,
                ],
            ],
            [
                'plan_key' => 'review-samsung-store-4m', 'subscription_group_id' => $groups['group_b'],
                'product_type' => 'marketplace_access', 'name' => 'Samsung store', 'tier_key' => 'samsung_store',
                'billing_interval' => 'monthly', 'duration_count' => 4, 'price_minor' => 2485000, 'currency' => 'NGN',
                'discount' => 0, 'bonus' => 0, 'subscription_referral_percent' => 0, 'task_referral_percent' => 6,
                'is_special' => 1, 'max_review_tasks' => 360, 'max_ad_shares' => 0,
                'eligible_app_store' => 'Samsung Galaxy Store', 'eligible_app_category' => null,
                'minimum_withdrawal_minor' => 300000, 'withdrawal_days' => ['Friday'],
                'daily_revenue_minor' => 99000, 'task_reward_minor' => 33000, 'daily_task_limit' => 3,
                'features' => [
                    'amount' => 24850,
                    'minimum_withdrawal' => 3000,
                    'withdrawal_days' => ['Friday'],
                    'daily_revenue' => 990,
                    'per_survey' => 330,
                    'daily_task_limit' => 3,
                ],
            ],
            [
                'plan_key' => 'review-apple-store-4m', 'subscription_group_id' => $groups['group_c'],
                'product_type' => 'marketplace_access', 'name' => 'Apple store', 'tier_key' => 'apple_store',
                'billing_interval' => 'monthly', 'duration_count' => 4, 'price_minor' => 5775000, 'currency' => 'NGN',
                'discount' => 0, 'bonus' => 0, 'subscription_referral_percent' => 0, 'task_referral_percent' => 6,
                'is_special' => 0, 'max_review_tasks' => 360, 'max_ad_shares' => 0,
                'eligible_app_store' => null, 'eligible_app_category' => 'Apple',
                'minimum_withdrawal_minor' => 300000, 'withdrawal_days' => ['Friday', 'Wednesday'],
                'daily_revenue_minor' => 210000, 'task_reward_minor' => 70000, 'daily_task_limit' => 3,
                'features' => [
                    'amount' => 57750,
                    'minimum_withdrawal' => 3000,
                    'withdrawal_days' => ['Friday', 'Wednesday'],
                    'daily_revenue' => 2100,
                    'per_survey' => 700,
                    'daily_task_limit' => 3,
                ],
            ],
        ];

        // The location add-on plans are no longer offered. Their historical
        // subscription rows are retained rather than deleted.
        DB::table('subscription_plans')->where('product_type', 'location_coverage')->update([
            'is_active' => false,
            'updated_at' => $now,
        ]);

        foreach ($plans as $plan) {
            $features = $plan['features'];
            unset($plan['features']);
            $plan['features'] = json_encode($features, JSON_THROW_ON_ERROR);
            $plan['withdrawal_days'] = $plan['withdrawal_days'] === null
                ? null
                : json_encode($plan['withdrawal_days'], JSON_THROW_ON_ERROR);
            $plan['is_active'] = true;
            $plan['created_at'] = $now;
            $plan['updated_at'] = $now;

            DB::table('subscription_plans')->upsert(
                [$plan],
                ['plan_key'],
                [
                    'subscription_group_id', 'product_type', 'name', 'tier_key', 'billing_interval', 'duration_count',
                    'price_minor', 'currency', 'discount', 'bonus', 'is_special',
                    'subscription_referral_percent', 'task_referral_percent', 'max_review_tasks', 'max_ad_shares',
                    'eligible_app_store', 'eligible_app_category', 'minimum_withdrawal_minor',
                    'withdrawal_days', 'daily_revenue_minor', 'task_reward_minor', 'daily_task_limit', 'features',
                    'is_active', 'updated_at',
                ],
            );

            $planId = DB::table('subscription_plans')->where('plan_key', $plan['plan_key'])->value('id');
            DB::table('subscription_plan_features')->where('subscription_plan_id', $planId)->delete();
            $featureRows = [];
            foreach ($features as $key => $value) {
                $featureRows[] = [
                    'subscription_plan_id' => $planId,
                    'feature_key' => $key,
                    'label' => ucwords(str_replace('_', ' ', $key)),
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('subscription_plan_features')->insert($featureRows);
        }
    }
}
