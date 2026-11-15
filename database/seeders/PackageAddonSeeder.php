<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageAddonSeeder extends Seeder
{
    public function run(): void
    {
        $offers = [
            [
				'plan_key' => 'review-google-play-4m', 'addon_key' => 'playstore', 'name' => 'Playstore', 
				'price_minor' => 750, 'discount' => 2,
                'features' => [
                    'bandwidth' => 10240000000,
                    'SSO_and_SCIM' => true,
                    'location' => ['United State', 'Nigeria'],
                    'phone_no' => false,
                    'email_support' => true,
                    'dedicated_ips' => true,
                    'encrypted_tunnel' => true,
                    'country_code' => ['UK','NG'],
                ],
			],
            [
				'plan_key' => 'review-samsung-store-4m', 'addon_key' => 'galaxy', 'name' => 'Galaxy', 
				'price_minor' => 850, 'discount' => 5,
                'features' => [
                    'bandwidth' => 20480000000,
                    'SSO_and_SCIM' => true,
                    'location' => ['United Kingdom', 'North America'],
                    'phone_no' => false,
                    'email_support' => true,
                    'dedicated_ips' => true,
                    'encrypted_tunnel' => true,
                    'country_code' => ['UK','NA'],
                ],
			],
            [
				'plan_key' => 'review-apple-store-4m', 'addon_key' => 'apple', 'name' => 'Apple', 
				'price_minor' => 1150, 'discount' => 10,
                'features' => [
                    'bandwidth' => 40960000000,
                    'SSO_and_SCIM' => true,
                    'location' => ['United State', 'United Kingdom', 'Canada'],
                    'phone_no' => true,
                    'email_support' => true,
                    'dedicated_ips' => true,
                    'encrypted_tunnel' => true,
                    'country_code' => ['US','UK','CA'],
                ],
			],
        ];

        foreach ($offers as $offer) {
            $planId = DB::table('subscription_plans')->where('plan_key', $offer['plan_key'])->value('id');
            if (! $planId) continue;
            DB::table('subscription_plans')->where('id', $planId)->update(['addons' => true, 'updated_at' => now()]);
            unset($offer['plan_key']);
            $offer['features'] = json_encode($offer['features'], JSON_THROW_ON_ERROR);
            DB::table('package_addons')->insertOrIgnore([
                [...$offer, 'subscription_plan_id' => $planId, 'currency' => 'USD', 'billing_interval' => 'monthly', 'duration_count' => 1, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
            ]);
        }
    }
}
