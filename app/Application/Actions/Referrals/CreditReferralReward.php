<?php

namespace App\Application\Actions\Referrals;

use App\Application\Actions\Wallets\CreditUserWallet;
use App\Application\Money\CurrencyMinorUnits;
use App\Enums\WalletType;
use App\Models\ReferralAttribution;
use App\Models\ReferralReward;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;

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
    ): array {
        $setting = $rewardType === 'subscription'
            ? 'rewards.referral_subscription_active'
            : 'rewards.referral_task_active';
        if (! $this->enabled($setting)) {
            return [];
        }

        $attribution = ReferralAttribution::query()->where('referred_user_id', $referredUser->id)->first();
        if (! $attribution) {
            return [];
        }

        if (! $plan) {
            $effectiveAt ??= now();
            $plan = UserSubscription::query()->with('plan')
                ->where('user_id', $referredUser->id)
                ->where('product_type', 'marketplace_access')
                ->where('starts_at', '<=', $effectiveAt)->where('ends_at', '>', $effectiveAt)
                ->latest('ends_at')->first()?->plan;
        }
        if (! $plan) {
            return [];
        }

        $now = now();
        $schedule = $rewardType === 'subscription'
            ? ($plan->subscription_referral_tiers ?? [])
            : ($plan->task_referral_tiers ?? []);
        $legacyPercent = $rewardType === 'subscription'
            ? (float) $plan->subscription_referral_percent
            : (float) $plan->task_referral_percent;
        $visited = [$referredUser->id => true];
        $currentAttribution = $attribution;
        $rewards = [];

        for ($level = 1; $level <= 3 && $currentAttribution; $level++) {
            $beneficiaryId = (int) $currentAttribution->referrer_user_id;
            if (isset($visited[$beneficiaryId])) {
                break;
            }
            $visited[$beneficiaryId] = true;

            $percent = (float) ($schedule[(string) $level] ?? $schedule[$level] ?? ($level === 1 ? $legacyPercent : 0));
            $amountMinor = (int) round($baseAmountMinor * min(max($percent, 0), 100) / 100);
            $beneficiary = $amountMinor > 0 ? User::query()->find($beneficiaryId) : null;
            if ($amountMinor > 0 && $beneficiary) {
                $levelIdempotencyKey = $level === 1
                    ? $idempotencyKey
                    : $idempotencyKey.':level:'.$level;
                $ledger = app(CreditUserWallet::class)->handle(
                    $beneficiary,
                    WalletType::Referral,
                    $amountMinor,
                    strtoupper($currency),
                    'referral_'.$rewardType.'_reward',
                    $sourceType,
                    $sourceId,
                    $levelIdempotencyKey,
                    $description.' (Level '.$level.')',
                    $now,
                );
                $wallet = $ledger->wallet;

                $reward = ReferralReward::query()->firstOrCreate(
                    [
                        'referral_attribution_id' => $attribution->id,
                        'beneficiary_user_id' => $beneficiary->id,
                        'referral_level' => $level,
                        'reward_type' => $rewardType,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                    ],
                    [
                        'subscription_plan_id' => $plan->id,
                        'wallet_id' => $wallet->id,
                        'ledger_entry_id' => $ledger->id,
                        'amount_minor' => (int) $ledger->amount_minor,
                        'currency' => strtoupper($wallet->currency),
                        'reward_percent' => $percent,
                        'base_amount_minor' => $baseAmountMinor,
                        'base_currency' => strtoupper($currency),
                        'status' => 'paid',
                    ],
                );
                $rewards[] = $reward;

                $creditedCurrency = strtoupper($wallet->currency);
                Transaction::query()->updateOrCreate(
                    ['reference' => 'ledger:'.$ledger->id],
                    [
                        'user_id' => $beneficiary->id,
                        'type' => 'referral_bonus',
                        'direction' => 'credit',
                        'amount' => CurrencyMinorUnits::fromMinor((int) $ledger->amount_minor, $creditedCurrency),
                        'fee' => 0,
                        'wallet' => $wallet->wallet_type->value,
                        'status' => $ledger->status->value === 'posted' ? 'completed' : $ledger->status->value,
                        'description' => $ledger->description ?: $description.' (Level '.$level.')',
                        'meta' => array_merge($ledger->metadata ?? [], [
                            'currency' => $creditedCurrency,
                            'currency_minor_units' => CurrencyMinorUnits::precision($creditedCurrency),
                            'amount_minor' => (int) $ledger->amount_minor,
                            'referral_reward_id' => $reward->id,
                            'referral_level' => $level,
                            'reward_percent' => $reward->reward_percent,
                            'base_amount_minor' => (int) ($reward->base_amount_minor ?? $baseAmountMinor),
                            'base_currency' => strtoupper((string) ($reward->base_currency ?: $currency)),
                            'subscription_plan_id' => $reward->subscription_plan_id,
                            'reward_type' => $rewardType,
                            'source_type' => $sourceType,
                            'source_id' => $sourceId,
                        ]),
                    ],
                );
            }

            $currentAttribution = ReferralAttribution::query()
                ->where('referred_user_id', $beneficiaryId)
                ->first();
        }

        if ($rewards !== [] && $attribution->status !== 'qualified') {
            $attribution->forceFill(['status' => 'qualified', 'qualified_at' => $now])->save();
        }

        return $rewards;
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
