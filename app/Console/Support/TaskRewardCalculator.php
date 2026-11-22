<?php

namespace App\Console\Support;

use App\Application\Actions\Marketplace\DailyShareRevenue;
use App\Application\Money\CurrencyConverter;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\AdShareTask;
use App\Models\SubscriptionPlan;
use App\Models\User;

/** Calculates package-aware task rewards and daily share earning caps. */
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
        try {
            return app(CurrencyConverter::class)
                ->convertMinor($amountMinor, $from, $to)['amount_minor'];
        } catch (\Illuminate\Validation\ValidationException) {
            return null;
        }
    }
}
