<?php

namespace App\Application\Actions\Rankings;

use App\Application\Money\CurrencyMinorUnits;
use App\Application\Actions\Wallets\CreditUserWallet;
use App\Enums\WalletType;
use App\Models\SystemSetting;
use App\Models\UserRankQualification;
use Illuminate\Support\Facades\DB;

final class RewardDueRanks
{
    public function handle(): int
    {
        $setting = SystemSetting::query()->where('setting_key', 'rewards.user_ranking_active')->value('value');
        if (! filter_var(is_array($setting) ? ($setting['enabled'] ?? false) : $setting, FILTER_VALIDATE_BOOLEAN)) return 0;

        $rewarded = 0;
        UserRankQualification::query()->with('level')
            ->where('status', 'active')->where('next_reward_at', '<=', now())
            ->orderBy('next_reward_at')->chunkById(100, function ($qualifications) use (&$rewarded): void {
                foreach ($qualifications as $qualification) {
                    DB::transaction(function () use ($qualification, &$rewarded): void {
                        $locked = UserRankQualification::query()->lockForUpdate()->with('level')->find($qualification->id);
                        if (! $locked || $locked->status !== 'active' || ! $locked->next_reward_at || $locked->next_reward_at->isFuture()) return;

                        $level = $locked->level;
                        $periodNumber = (int) $locked->rewards_paid + 1;
                        $entry = app(CreditUserWallet::class)->handle(
                            $locked->user,
                            WalletType::Bonus,
                            CurrencyMinorUnits::toMinor($locked->monthly_reward_usd_at_qualification, 'USD'),
                            'USD',
                            'rank_earning',
                            'user_rank_qualification',
                            $locked->id,
                            'rank-monthly:'.$locked->id.':'.$periodNumber,
                            $level->name.' rank monthly reward ('.$periodNumber.'/'.$locked->reward_duration_months_at_qualification.')',
                            now(),
                        );

                        if (! $entry->wasRecentlyCreated) {
                            $locked->next_reward_at = $locked->next_reward_at->copy()->addMonthNoOverflow();
                            $locked->save();
                            return;
                        }

                        $locked->rewards_paid = $periodNumber;
                        $locked->status = $periodNumber >= $locked->reward_duration_months_at_qualification ? 'completed' : 'active';
                        $locked->next_reward_at = $locked->status === 'active'
                            ? $locked->next_reward_at->copy()->addMonthNoOverflow()
                            : null;
                        $locked->save();
                        $rewarded++;
                    });
                }
            });

        return $rewarded;
    }
}
