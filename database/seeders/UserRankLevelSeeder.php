<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRankLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [1, 'Starter', 10, 100, 10, 5, 3],
            [2, 'Bronze', 25, 250, 25, 7, 4],
            [3, 'Silver', 50, 600, 50, 15, 5],
            [4, 'Platinum', 100, 1500, 120, 25, 6],
            [5, 'Elite', 250, 4000, 250, 40, 8],
            [6, 'Ambassador', 500, 10000, 500, 60, 12],
        ];

        foreach ($levels as [$level, $name, $referrals, $referralInflow, $userInflow, $reward, $duration]) {
            DB::table('user_rank_levels')->updateOrInsert(['level' => $level], [
                'name' => $name,
                'icon' => 'assets/ranks/'.strtolower($name).'.svg',
                'minimum_subscribed_referrals' => $referrals,
                'minimum_referral_cash_inflow_usd' => $referralInflow,
                'minimum_user_cash_inflow_usd' => $userInflow,
                'monthly_reward_usd' => $reward,
                'reward_duration_months' => $duration,
                'is_active' => true,
                'sort_order' => $level,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
