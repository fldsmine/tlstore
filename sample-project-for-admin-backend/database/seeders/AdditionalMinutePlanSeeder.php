<?php

namespace Database\Seeders;

use App\Models\AdditionalMinutePlan;
use Illuminate\Database\Seeder;

class AdditionalMinutePlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => '+30 Minutes', 'description' => '30 extra daily call minutes + 1 phone number for 30 days.', 'price' => 3.00, 'agent_price' => 2.40, 'foreign_price' => 5.00, 'foreign_agent_price' => 4.00, 'daily_call_minutes' => 30, 'call_id_bonus' => 1, 'paylater_duration_days' => 14, 'min_earning_rate' => 0.05, 'referral_bonus' => 0.30, 'is_featured' => false, 'features' => ['30 extra daily minutes', '+1 phone number', 'Valid for 30 days']],
            ['name' => '+60 Minutes', 'description' => '60 extra daily call minutes + 2 phone numbers for 30 days.', 'price' => 5.50, 'agent_price' => 4.40, 'foreign_price' => 9.00, 'foreign_agent_price' => 7.20, 'daily_call_minutes' => 60, 'call_id_bonus' => 2, 'paylater_duration_days' => 18, 'min_earning_rate' => 0.05, 'referral_bonus' => 0.50, 'is_featured' => true, 'features' => ['60 extra daily minutes', '+2 phone numbers', 'Valid for 30 days', 'Best value']],
            ['name' => '+120 Minutes', 'description' => '120 extra daily call minutes + 4 phone numbers for 30 days.', 'price' => 10.00, 'agent_price' => 8.00, 'foreign_price' => 17.00, 'foreign_agent_price' => 13.60, 'daily_call_minutes' => 120, 'call_id_bonus' => 4, 'paylater_duration_days' => 21, 'min_earning_rate' => 0.05, 'referral_bonus' => 1.00, 'is_featured' => false, 'features' => ['120 extra daily minutes', '+4 phone numbers', 'Valid for 30 days']],
        ];

        foreach ($plans as $plan) {
            AdditionalMinutePlan::query()->updateOrCreate(['name' => $plan['name']], $plan + ['duration_days' => 30]);
        }
    }
}
