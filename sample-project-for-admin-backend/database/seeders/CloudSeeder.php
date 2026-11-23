<?php

namespace Database\Seeders;

use App\Models\CloudStoragePlan;
use Illuminate\Database\Seeder;

class CloudSeeder extends Seeder
{
    public function run(): void
    {
        // Existing purchasable storage plans — each pack grants extra contact slots AND extra
        // daily SMS access (extra_sms), on top of the package's base quotas.
        collect([
            ['name' => 'Starter Storage', 'description' => 'Room for 5 more sponsor contacts — yours for life.', 'token_price' => 3, 'agent_price' => 2.4, 'foreign_token_price' => 5, 'foreign_agent_price' => 4, 'extra_contacts' => 5, 'extra_sms' => 5, 'referral_bonus' => 0.5, 'duration_days' => 0, 'sort_order' => 0],
            ['name' => 'Plus Storage', 'description' => 'Room for 15 more sponsor contacts. Never expires.', 'token_price' => 10, 'agent_price' => 8, 'foreign_token_price' => 16, 'foreign_agent_price' => 13, 'extra_contacts' => 15, 'extra_sms' => 15, 'referral_bonus' => 0.5, 'duration_days' => 0, 'sort_order' => 1],
            ['name' => 'Monthly Storage Boost', 'description' => '10 extra contact slots for 30 days — top up when you need room.', 'token_price' => 4, 'agent_price' => 3.2, 'foreign_token_price' => 7, 'foreign_agent_price' => 5.6, 'extra_contacts' => 10, 'extra_sms' => 10, 'referral_bonus' => 0.5, 'duration_days' => 30, 'sort_order' => 2],
            ['name' => 'Quarterly Storage Boost', 'description' => '20 extra contact slots for 90 days.', 'token_price' => 9, 'agent_price' => 7.2, 'foreign_token_price' => 15, 'foreign_agent_price' => 12, 'extra_contacts' => 20, 'extra_sms' => 20, 'referral_bonus' => 0.5, 'duration_days' => 90, 'sort_order' => 3],
        ])->each(fn (array $p) => CloudStoragePlan::updateOrCreate(['name' => $p['name']], $p + ['is_active' => true]));

        // Special coupon storage plans (tier-specific, not purchasable) — they grant the same
        // dual quota (extra contacts + extra SMS) when activated with a special coupon code.
        collect([
            ['name' => 'Tier 1 Special Storage', 'description' => 'Special coupon storage for Diamond eSIM users.', 'extra_contacts' => 4, 'extra_sms' => 4, 'tier_level' => 1, 'is_active' => true, 'sort_order' => 100],
            ['name' => 'Tier 2 Special Storage', 'description' => 'Special coupon storage for Royal eSIM users.', 'extra_contacts' => 7, 'extra_sms' => 7, 'tier_level' => 2, 'is_active' => true, 'sort_order' => 101],
        ])->each(fn (array $p) => CloudStoragePlan::updateOrCreate(
            ['name' => $p['name']],
            $p + [
                'token_price' => 0,
                'agent_price' => 0,
                'foreign_token_price' => 0,
                'foreign_agent_price' => 0,
                'renewal_token_price' => 0,
                'renewal_agent_price' => 0,
                'foreign_renewal_token_price' => 0,
                'foreign_renewal_agent_price' => 0,
                'referral_bonus' => 0,
                'duration_days' => 0,
            ]
        ));
    }
}
