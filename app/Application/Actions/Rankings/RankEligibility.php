<?php

namespace App\Application\Actions\Rankings;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\ExchangeRateSnapshot;
use App\Models\PaymentIntent;
use App\Models\ReferralAttribution;
use App\Models\User;
use App\Models\UserRankLevel;
use Illuminate\Support\Facades\Cache;

final class RankEligibility
{
    /** @return array{subscribed_referrals:int,referral_cash_inflow_usd:float,user_cash_inflow_usd:float} */
    public function metrics(User $user): array
    {
        $referrals = ReferralAttribution::query()
            ->where('referrer_user_id', $user->id)
            ->whereHas('referredUser.subscriptions', fn ($query) => $query
                ->where('product_type', 'marketplace_access')
                ->whereIn('status', ['active', 'expired', 'cancelled', 'paused', 'past_due']))
            ->with('referredUser:id')
            ->get()
            ->pluck('referred_user_id')
            ->unique()
            ->values();

        return [
            'subscribed_referrals' => $referrals->count(),
            'referral_cash_inflow_usd' => $this->cashInflowUsd($referrals->all()),
            'user_cash_inflow_usd' => $this->cashInflowUsd([$user->id]),
        ];
    }

    /** @param list<int> $userIds */
    public function cashInflowUsd(array $userIds): float
    {
        if ($userIds === []) return 0.0;

        $payments = PaymentIntent::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->get(['amount_minor', 'currency']);

        $currencies = $payments->pluck('currency')->unique()->values();
        $rates = Cache::remember('ranking.usd-rates.'.sha1($currencies->implode(',')), now()->addHour(), function () use ($currencies): array {
            $result = ['USD' => 1.0];
            foreach ($currencies->reject(fn ($currency) => $currency === 'USD') as $currency) {
                $direct = ExchangeRateSnapshot::query()
                    ->where('base_currency', 'USD')->where('quote_currency', $currency)
                    ->latest('fetched_at')->value('rate');
                if ($direct !== null && (float) $direct > 0) {
                    $result[$currency] = 1 / (float) $direct;
                    continue;
                }
                $inverse = ExchangeRateSnapshot::query()
                    ->where('base_currency', $currency)->where('quote_currency', 'USD')
                    ->latest('fetched_at')->value('rate');
                if ($inverse !== null) $result[$currency] = (float) $inverse;
            }
            return $result;
        });

        $total = 0.0;
        foreach ($payments as $payment) {
            $currency = strtoupper($payment->currency);
            $total += CurrencyMinorUnits::fromMinor((int) $payment->amount_minor, $currency) * (float) ($rates[$currency] ?? 0);
        }

        return round($total, 2);
    }

    /** @return array<int,array<string,mixed>> */
    public function levelsFor(User $user): array
    {
        $metrics = $this->metrics($user);
        return UserRankLevel::query()->orderBy('sort_order')->get()->map(function (UserRankLevel $level) use ($metrics): array {
            return [
                'id' => $level->id,
                'level' => $level->level,
                'name' => $level->name,
                'icon' => $level->icon,
                'is_active' => $level->is_active,
                'requirements' => [
                    'subscribed_referrals' => (int) $level->minimum_subscribed_referrals,
                    'referral_cash_inflow_usd' => (float) $level->minimum_referral_cash_inflow_usd,
                    'user_cash_inflow_usd' => (float) $level->minimum_user_cash_inflow_usd,
                ],
                'progress' => $metrics,
                'monthly_reward_usd' => (float) $level->monthly_reward_usd,
                'reward_duration_months' => (int) $level->reward_duration_months,
                'eligible' => $level->is_active
                    && $metrics['subscribed_referrals'] >= $level->minimum_subscribed_referrals
                    && $metrics['referral_cash_inflow_usd'] >= (float) $level->minimum_referral_cash_inflow_usd
                    && $metrics['user_cash_inflow_usd'] >= (float) $level->minimum_user_cash_inflow_usd,
            ];
        })->all();
    }
}
