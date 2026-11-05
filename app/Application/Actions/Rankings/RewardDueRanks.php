<?php

namespace App\Application\Actions\Rankings;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\LedgerEntry;
use App\Models\SystemSetting;
use App\Models\UserRankQualification;
use App\Models\Wallet;
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
                        $wallet = Wallet::query()->firstOrCreate(
                            ['user_id' => $locked->user_id, 'wallet_type' => 'bonus', 'currency' => 'USD'],
                            ['status' => 'active'],
                        );
                        $periodNumber = (int) $locked->rewards_paid + 1;
                        $entry = LedgerEntry::query()->firstOrCreate(['idempotency_key' => 'rank-monthly:'.$locked->id.':'.$periodNumber], [
                            'wallet_id' => $wallet->id,
                            'entry_type' => 'rank_earning',
                            'direction' => 'credit',
                            'amount_minor' => CurrencyMinorUnits::toMinor($locked->monthly_reward_usd_at_qualification, 'USD'),
                            'status' => 'posted',
                            'source_type' => 'user_rank_qualification',
                            'source_id' => $locked->id,
                            'description' => $level->name.' rank monthly reward ('.$periodNumber.'/'.$locked->reward_duration_months_at_qualification.')',
                            'available_at' => now(),
                            'posted_at' => now(),
                        ]);

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
