<?php

namespace App\Application\Actions\SocialFollow;

use App\Application\Actions\Wallets\CreditUserWallet;
use App\Enums\WalletType;
use App\Models\SystemSetting;
use App\Models\SocialHandle;
use App\Models\User;
use App\Models\UserSocialFollow;
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
            $follow = UserSocialFollow::query()->create([
                'user_id' => $user->id,
                'social_handle_id' => $handle->id,
                'status' => 'completed',
                'reward_amount_minor' => $handle->reward_amount_minor,
                'currency' => $handle->currency,
                'followed_at' => $now,
            ]);

            $ledgerEntry = app(CreditUserWallet::class)->handle(
                $user,
                WalletType::Bonus,
                (int) $handle->reward_amount_minor,
                $handle->currency,
                'social_follow_reward',
                'user_social_follow',
                $follow->id,
                'social-follow:'.$user->id.':'.$handle->id,
                'Social follow reward: '.$handle->name,
                $now,
            );
            $follow->forceFill([
                'ledger_entry_id' => $ledgerEntry->id,
                'reward_amount_minor' => (int) $ledgerEntry->amount_minor,
                'currency' => $ledgerEntry->wallet->currency,
            ])->save();

            return $follow->load('socialHandle');
        });
    }
}
