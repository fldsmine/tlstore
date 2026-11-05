<?php

namespace App\Application\Actions\Referrals;

use App\Models\LedgerEntry;
use App\Models\ReferralAttribution;
use App\Models\ReferralReward;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\Wallet;

final class CreditReferralReward
{
    public function handle(
        User $referredUser,
        int $baseAmountMinor,
        string $currency,
        string $rewardType,
        string $sourceType,
        int $sourceId,
        string $idempotencyKey,
        string $description,
        ?SubscriptionPlan $plan = null,
        ?\DateTimeInterface $effectiveAt = null,
    ): ?ReferralReward {
        $setting = $rewardType === 'subscription'
            ? 'rewards.referral_subscription_active'
            : 'rewards.referral_task_active';
        if (! $this->enabled($setting)) {
            return null;
        }

        $attribution = ReferralAttribution::query()->where('referred_user_id', $referredUser->id)->first();
        if (! $attribution) {
            return null;
        }

        if (! $plan) {
            $effectiveAt ??= now();
            $plan = UserSubscription::query()->with('plan')
                ->where('user_id', $referredUser->id)
                ->where('product_type', 'marketplace_access')
                ->where('status', 'active')->where('starts_at', '<=', $effectiveAt)->where('ends_at', '>', $effectiveAt)
                ->latest('ends_at')->first()?->plan;
        }
        if (! $plan) {
            return null;
        }

        $percent = $rewardType === 'subscription'
            ? (float) $plan->subscription_referral_percent
            : (float) $plan->task_referral_percent;
        $amountMinor = (int) round($baseAmountMinor * min(max($percent, 0), 100) / 100);
        if ($amountMinor < 1) {
            return null;
        }

        $now = now();
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $attribution->referrer_user_id, 'wallet_type' => 'referral', 'currency' => strtoupper($currency)],
            ['status' => 'active'],
        );
        $ledger = LedgerEntry::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], [
            'wallet_id' => $wallet->id,
            'entry_type' => 'referral_'.$rewardType.'_reward',
            'direction' => 'credit',
            'amount_minor' => $amountMinor,
            'status' => 'posted',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => $description,
            'available_at' => $now,
            'posted_at' => $now,
        ]);

        $reward = ReferralReward::query()->firstOrCreate(
            [
                'referral_attribution_id' => $attribution->id,
                'reward_type' => $rewardType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'wallet_id' => $wallet->id,
                'ledger_entry_id' => $ledger->id,
                'amount_minor' => $amountMinor,
                'currency' => strtoupper($currency),
                'status' => 'paid',
            ],
        );

        if ($attribution->status !== 'qualified') {
            $attribution->forceFill(['status' => 'qualified', 'qualified_at' => $now])->save();
        }

        return $reward;
    }

    public function enabled(string $key): bool
    {
        $value = SystemSetting::query()->where('setting_key', $key)->value('value');
        if ($value === null) {
            return true;
        }
        if (is_array($value)) {
            $value = $value['enabled'] ?? true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
