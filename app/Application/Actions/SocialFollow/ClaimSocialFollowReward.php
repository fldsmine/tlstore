<?php

namespace App\Application\Actions\SocialFollow;

use App\Models\SystemSetting;
use App\Models\LedgerEntry;
use App\Models\SocialHandle;
use App\Models\User;
use App\Models\UserSocialFollow;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClaimSocialFollowReward
{
    public function handle(User $user, SocialHandle $socialHandle): UserSocialFollow
    {
        return DB::transaction(function () use ($user, $socialHandle): UserSocialFollow {
            $handle = SocialHandle::query()->lockForUpdate()->findOrFail($socialHandle->id);
            if (! $handle->is_active) {
                throw ValidationException::withMessages(['social_handle' => 'This social task is no longer active.']);
            }
            $rewardSetting = SystemSetting::query()->where('setting_key', 'rewards.social_follow_active')->value('value');
            $rewardsEnabled = $rewardSetting === null || filter_var(is_array($rewardSetting) ? ($rewardSetting['enabled'] ?? true) : $rewardSetting, FILTER_VALIDATE_BOOLEAN);
            if (! $rewardsEnabled) {
                throw ValidationException::withMessages(['social_handle' => 'Social follow rewards are temporarily disabled.']);
            }

            $existing = UserSocialFollow::query()
                ->where('user_id', $user->id)
                ->where('social_handle_id', $handle->id)
                ->first();
            if ($existing) {
                return $existing->load('socialHandle');
            }

            $now = now();
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $user->id, 'wallet_type' => 'bonus', 'currency' => $handle->currency],
                ['status' => 'active'],
            );
            $follow = UserSocialFollow::query()->create([
                'user_id' => $user->id,
                'social_handle_id' => $handle->id,
                'status' => 'completed',
                'reward_amount_minor' => $handle->reward_amount_minor,
                'currency' => $handle->currency,
                'followed_at' => $now,
            ]);

            $ledgerEntry = LedgerEntry::query()->create([
                'wallet_id' => $wallet->id,
                'entry_type' => 'social_follow_reward',
                'direction' => 'credit',
                'amount_minor' => $handle->reward_amount_minor,
                'status' => 'posted',
                'source_type' => 'user_social_follow',
                'source_id' => $follow->id,
                'idempotency_key' => 'social-follow:'.$user->id.':'.$handle->id,
                'description' => 'Social follow reward: '.$handle->name,
                'available_at' => $now,
                'posted_at' => $now,
            ]);
            $follow->forceFill(['ledger_entry_id' => $ledgerEntry->id])->save();

            return $follow->load('socialHandle');
        });
    }
}
