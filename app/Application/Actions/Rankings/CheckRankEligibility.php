<?php

namespace App\Application\Actions\Rankings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRankLevel;
use App\Models\UserRankQualification;
use Illuminate\Support\Facades\DB;

final class CheckRankEligibility
{
    public function __construct(private readonly RankEligibility $eligibility) {}

    public function enabled(): bool
    {
        $value = SystemSetting::query()->where('setting_key', 'rewards.user_ranking_active')->value('value');
        return filter_var(is_array($value) ? ($value['enabled'] ?? false) : $value, FILTER_VALIDATE_BOOLEAN);
    }

    public function handle(?int $userId = null): int
    {
        if (! $this->enabled()) return 0;

        $created = 0;
        $users = User::query()->where('role', 'reviewer')->where('status', 'active')->when($userId, fn ($query) => $query->whereKey($userId));
        $users->chunkById(100, function ($batch) use (&$created): void {
            foreach ($batch as $user) {
                $metrics = $this->eligibility->metrics($user);
                foreach (UserRankLevel::query()->where('is_active', true)->orderBy('level')->get() as $level) {
                    if ($metrics['subscribed_referrals'] < $level->minimum_subscribed_referrals
                        || $metrics['referral_cash_inflow_usd'] < (float) $level->minimum_referral_cash_inflow_usd
                        || $metrics['user_cash_inflow_usd'] < (float) $level->minimum_user_cash_inflow_usd) {
                        continue;
                    }

                    $created += DB::table('user_rank_qualifications')->insertOrIgnore([
                        'user_id' => $user->id,
                        'user_rank_level_id' => $level->id,
                        'subscribed_referrals_at_qualification' => $metrics['subscribed_referrals'],
                        'referral_cash_inflow_usd_at_qualification' => $metrics['referral_cash_inflow_usd'],
                        'user_cash_inflow_usd_at_qualification' => $metrics['user_cash_inflow_usd'],
                        'monthly_reward_usd_at_qualification' => $level->monthly_reward_usd,
                        'reward_duration_months_at_qualification' => $level->reward_duration_months,
                        'qualified_at' => now(),
                        'next_reward_at' => now()->startOfMonth()->addMonth(),
                        'rewards_paid' => 0,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return $created;
    }
}
