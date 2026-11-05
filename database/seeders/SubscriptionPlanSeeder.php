<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $plans = [
            [
                'plan_key' => 'demo-market-lite',
                'product_type' => 'marketplace_access',
                'name' => 'Lite',
                'tier_key' => 'lite',
                'billing_interval' => 'monthly',
                'duration_count' => 1,
                'price_minor' => 499,
                'currency' => 'USD',
                'discount' => 0.00,
                'bonus' => 0.00,
                'subscription_referral_percent' => 5.00,
                'task_referral_percent' => 3.00,
                'is_special' => 0,
                'max_review_tasks' => 20,
                'max_ad_shares' => 50,
                'global_listing_access' => false,
                'features' => [
                    'review_tasks_per_month' => 20,
                    'ad_shares_per_month' => 50,
                    'listing_access' => 'registered_country',
                    'location_addon_required_for_other_markets' => true,
                ],
            ],
            [
                'plan_key' => 'demo-market-premium',
                'product_type' => 'marketplace_access',
                'name' => 'Premium',
                'tier_key' => 'premium',
                'billing_interval' => 'monthly',
                'duration_count' => 1,
                'price_minor' => 1499,
                'currency' => 'USD',
                'discount' => 10.00,
                'bonus' => 2.00,
                'subscription_referral_percent' => 5.00,
                'task_referral_percent' => 3.00,
                'is_special' => 1,
                'max_review_tasks' => 100,
                'max_ad_shares' => 250,
                'global_listing_access' => false,
                'features' => [
                    'review_tasks_per_month' => 100,
                    'ad_shares_per_month' => 250,
                    'listing_access' => 'registered_country_and_covered_regions',
                    'location_addon_required_for_other_markets' => true,
                    'welcome_bonus_display_amount' => 2.00,
                ],
            ],
            [
                'plan_key' => 'demo-market-gold',
                'product_type' => 'marketplace_access',
                'name' => 'Gold',
                'tier_key' => 'gold',
                'billing_interval' => 'monthly',
                'duration_count' => 1,
                'price_minor' => 2999,
                'currency' => 'USD',
                'discount' => 15.00,
                'bonus' => 5.00,
                'subscription_referral_percent' => 5.00,
                'task_referral_percent' => 3.00,
                'is_special' => 1,
                'max_review_tasks' => 500,
                'max_ad_shares' => 1000,
                'global_listing_access' => true,
                'features' => [
                    'review_tasks_per_month' => 500,
                    'ad_shares_per_month' => 1000,
                    'listing_access' => 'global',
                    'location_addon_required_for_other_markets' => false,
                    'welcome_bonus_display_amount' => 5.00,
                ],
            ],
            [
                'plan_key' => 'demo-location-starter-weekly',
                'product_type' => 'location_coverage',
                'name' => 'Starter',
                'tier_key' => 'starter',
                'billing_interval' => 'weekly',
                'duration_count' => 1,
                'price_minor' => 299,
                'currency' => 'USD',
                'discount' => 0.00,
                'bonus' => 0.00,
                'subscription_referral_percent' => 5.00,
                'task_referral_percent' => 3.00,
                'is_special' => 0,
                'max_review_tasks' => null,
                'max_ad_shares' => null,
                'global_listing_access' => false,
                'coverage' => [['target_type' => 'country', 'target_code' => 'NG']],
                'features' => [
                    'duration' => 'weekly',
                    'coverage' => ['NG'],
                    'coverage_description' => 'Nigeria only',
                ],
            ],
            [
                'plan_key' => 'demo-location-basic-monthly',
                'product_type' => 'location_coverage',
                'name' => 'Basic',
                'tier_key' => 'basic',
                'billing_interval' => 'monthly',
                'duration_count' => 1,
                'price_minor' => 799,
                'currency' => 'USD',
                'discount' => 5.00,
                'bonus' => 1.00,
                'subscription_referral_percent' => 5.00,
                'task_referral_percent' => 3.00,
                'is_special' => 0,
                'max_review_tasks' => null,
                'max_ad_shares' => null,
                'global_listing_access' => false,
                'coverage' => [['target_type' => 'region', 'target_code' => 'WEST_AFRICA']],
                'features' => [
                    'duration' => 'monthly',
                    'coverage' => ['WEST_AFRICA'],
                    'coverage_description' => 'West Africa region',
                ],
            ],
            [
                'plan_key' => 'demo-location-premium-quarterly',
                'product_type' => 'location_coverage',
                'name' => 'Premium',
                'tier_key' => 'premium',
                'billing_interval' => 'quarterly',
                'duration_count' => 1,
                'price_minor' => 2499,
                'currency' => 'USD',
                'discount' => 10.00,
                'bonus' => 3.00,
                'subscription_referral_percent' => 5.00,
                'task_referral_percent' => 3.00,
                'is_special' => 1,
                'max_review_tasks' => null,
                'max_ad_shares' => null,
                'global_listing_access' => false,
                'coverage' => [['target_type' => 'region', 'target_code' => 'GLOBAL']],
                'features' => [
                    'duration' => 'quarterly',
                    'coverage' => ['GLOBAL'],
                    'coverage_description' => 'All configured markets',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            $features = $plan['features'];
            $coverage = $plan['coverage'] ?? [];
            unset($plan['coverage']);
            unset($plan['features']);
            $plan['features'] = json_encode($features, JSON_THROW_ON_ERROR);
            $plan['is_active'] = true;
            $plan['created_at'] = $now;
            $plan['updated_at'] = $now;

            DB::table('subscription_plans')->upsert(
                [$plan],
                ['plan_key'],
                [
                    'product_type', 'name', 'tier_key', 'billing_interval', 'duration_count',
                    'price_minor', 'currency', 'discount', 'bonus', 'is_special', 'subscription_referral_percent', 'task_referral_percent',
                    'max_review_tasks', 'max_ad_shares', 'global_listing_access', 'features',
                    'is_active', 'updated_at',
                ]
            );

            $planId = DB::table('subscription_plans')->where('plan_key', $plan['plan_key'])->value('id');
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

            DB::table('subscription_plan_features')->upsert(
                $featureRows,
                ['subscription_plan_id', 'feature_key'],
                ['label', 'value', 'updated_at']
            );

            $coverageRows = array_map(static fn (array $target): array => [
                'subscription_plan_id' => $planId,
                'target_type' => $target['target_type'],
                'target_code' => $target['target_code'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $coverage);

            if ($coverageRows !== []) {
                DB::table('plan_location_coverage')->upsert(
                    $coverageRows,
                    ['subscription_plan_id', 'target_type', 'target_code'],
                    ['updated_at']
                );
            }
        }
    }
}
