<?php

namespace Database\Seeders;

use App\Models\CallType;
use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $callTypes = CallType::all()->keyBy('key');

        $maxDailyShareholderEarning = 0;

        $packages = [
            [
                'name' => 'Diamond eSIM',
                'description' => 'Ideal for beginners looking for a steady entry into the earning platform.',
                'price' => 10.50,
                'agent_price' => 8,
                'foreign_price' => 18,
                'foreign_agent_price' => 15,
                'direct_referral_bonus' => 1,
                'indirect_referral_bonus' => 0.3,
                'tier_level' => 1,
                'daily_call_minutes' => 30,
                'max_daily_shareholder_earning' => $maxDailyShareholderEarning,
                'call_id_limit' => 8,
                'is_featured' => false,
                'enable_cloud' => true,
                'cloud_calls' => false,
                'cloud_sms' => true,
                'cloud_contact_limit' => 0,
                'cloud_sms_limit' => 10,
                'success_message' => 'Congratulations! Your Diamond eSIM has been successfully activated! You can now receive text messages, and start earning.',
                'features' => [
                    'Access to allocated phone numbers',
                    'Earn per minute of call activity',
                    'Real-time earnings updates',
                    'Personalized earnings dashboard',
                    'Basic earning capacity',
                ],
                'call_types' => ['on_net' => [], 'off_net' => [], 'broadband' => []],
            ],
            [
                'name' => 'Royal eSIM',
                'description' => 'For users ready to maximize earnings with higher minutes and increased earning.',
                'price' => 17.50,
                'agent_price' => 16,
                'foreign_price' => 30,
                'foreign_agent_price' => 26,
                'direct_referral_bonus' => 2.5,
                'indirect_referral_bonus' => 0.75,
                'upgrade_price' => 7.5,                    // ≈ ₦7,500 to upgrade to Royal
                'upgrade_agent_price' => 7.5,              // vendor cost to mint a Royal upgrade code
                'upgrade_direct_referral_bonus' => 3.2,    // ≈ ₦3,200 direct upline bonus on upgrade
                'upgrade_indirect_referral_bonus' => 0.3,  // ≈ ₦300 indirect upline bonus on upgrade
                'tier_level' => 2,
                'daily_call_minutes' => 90,
                'max_daily_shareholder_earning' => $maxDailyShareholderEarning,
                'call_id_limit' => 15,
                'is_featured' => true,
                'enable_cloud' => true,
                'cloud_calls' => true,
                'cloud_sms' => true,
                'cloud_contact_limit' => 10,
                'cloud_sms_limit' => 20,
                'cloud_daily_earning_cap' => 5,
                'cloud_hourly_earning_cap' => 1,
                'success_message' => 'Congratulations! Your Royal eSIM has been successfully activated! You can now make and receive calls, receive text messages, and start earning.',
                'features' => [
                    'More allocated phone numbers',
                    'Higher earning per minute',
                    'Increased daily earning capacity',
                    'Full access to earning features',
                    'Priority access to new feature',
                ],
                'call_types' => ['on_net' => [], 'off_net' => [], 'broadband' => [], 'international' => []],
            ],
        ];

        foreach ($packages as $data) {
            $types = $data['call_types'];
            unset($data['call_types']);

            $package = Package::create($data);

            $attach = [];
            foreach ($types as $key => $override) {
                $attach[$callTypes[$key]->id] = [
                    'is_available' => true,
                    'rate_per_minute' => $override['rate'] ?? null,
                    'claimable_percentage' => $override['claim'] ?? null,
                ];
            }

            $package->callTypes()->attach($attach);
        }
    }
}
