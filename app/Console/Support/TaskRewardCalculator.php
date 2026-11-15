<?php

namespace App\Console\Support;

use App\Application\Actions\Marketplace\DailyShareRevenue;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\AdShareTask;
use App\Models\ExchangeRateSnapshot;
use App\Models\SubscriptionPlan;
use App\Models\User;

/** Demo-only reward caps derived from the user's active marketplace package. */
final class TaskRewardCalculator
{
    public function forAdShare(User $user, AdShareTask $task, SubscriptionPlan $plan): ?int
    {
        $taskCurrency = strtoupper((string) $task->ad?->currency);
        $reward = (int) $task->reserved_reward_minor;
        $packageReward = $this->packageRewardMinor($plan, ['per_share', 'per_task', 'per_survey']);
        if ($packageReward !== null) {
            $converted = $this->convertMinor($packageReward, $plan->currency, $taskCurrency);
            if ($converted === null) return null;
            $reward = min($reward, $converted);
        }

        if ($plan->group?->group_key === 'group_a' && $plan->daily_revenue_minor !== null) {
            $dailyRevenue = app(DailyShareRevenue::class);
            $usedIncludingThisTask = $dailyRevenue->usedTodayMinor($user, $plan->currency);
            $thisTaskInPlanCurrency = $this->convertMinor((int) $task->reserved_reward_minor, $taskCurrency, $plan->currency);
            if ($usedIncludingThisTask === null || $thisTaskInPlanCurrency === null) return null;
            // Submitted tasks reserve today's cap. Exclude the current task's
            // reservation so the reward can be recalculated without counting
            // its original amount twice.
            $usedBeforeThisTask = max(0, $usedIncludingThisTask - $thisTaskInPlanCurrency);
            $remaining = max(0, (int) $plan->daily_revenue_minor - $usedBeforeThisTask);
            if ($remaining === 0) return 0;
            $convertedRemaining = $this->convertMinor($remaining, $plan->currency, $taskCurrency);
            if ($convertedRemaining === null) return null;
            $reward = min($reward, $convertedRemaining);
        }

        return max(0, $reward);
    }

    public function forReview(int $taskRewardMinor, string $taskCurrency, SubscriptionPlan $plan): ?int
    {
        $packageReward = $this->packageRewardMinor($plan, ['per_survey']);
        if ($packageReward === null) return $taskRewardMinor;
        $converted = $this->convertMinor($packageReward, $plan->currency, $taskCurrency);

        return $converted === null ? null : max(0, min($taskRewardMinor, $converted));
    }

    /** @param list<string> $featureKeys */
    private function packageRewardMinor(SubscriptionPlan $plan, array $featureKeys): ?int
    {
        if ($plan->task_reward_minor !== null) return (int) $plan->task_reward_minor;

        $raw = $plan->getRawOriginal('features');
        $features = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : []);
        if (! is_array($features)) return null;
        foreach ($featureKeys as $key) {
            if (isset($features[$key]) && is_numeric($features[$key])) {
                return CurrencyMinorUnits::toMinor($features[$key], $plan->currency);
            }
        }

        return null;
    }

    private function convertMinor(int $amountMinor, string $from, string $to): ?int
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) return $amountMinor;
        $rate = $this->rate($from, $to);
        if ($rate === null) return null;

        return CurrencyMinorUnits::toMinor(
            CurrencyMinorUnits::fromMinor($amountMinor, $from) * $rate,
            $to,
        );
    }

    private function rate(string $from, string $to): ?float
    {
        $direct = $this->latestRate($from, $to);
        if ($direct !== null) return $direct;
        $inverse = $this->latestRate($to, $from);
        if ($inverse !== null && $inverse > 0) return 1 / $inverse;

        if ($from !== 'USD' && $to !== 'USD') {
            $fromUsd = $this->latestRate($from, 'USD');
            $usdTo = $this->latestRate('USD', $to);
            if ($fromUsd !== null && $usdTo !== null) return $fromUsd * $usdTo;
            $inverseFromUsd = $this->latestRate('USD', $from);
            $inverseUsdTo = $this->latestRate($to, 'USD');
            if ($inverseFromUsd !== null && $inverseUsdTo !== null && $inverseFromUsd > 0 && $inverseUsdTo > 0) {
                return (1 / $inverseFromUsd) * (1 / $inverseUsdTo);
            }
        }

        return null;
    }

    private function latestRate(string $base, string $quote): ?float
    {
        $rate = ExchangeRateSnapshot::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->value('rate');

        return $rate !== null && (float) $rate > 0 ? (float) $rate : null;
    }
}
