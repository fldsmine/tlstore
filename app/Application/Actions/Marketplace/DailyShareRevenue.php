<?php

namespace App\Application\Actions\Marketplace;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\ExchangeRateSnapshot;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Calculates approved and pending ad-share revenue against a user's daily cap. */
final class DailyShareRevenue
{
    /** @var array<string, float|null> */
    private array $rateCache = [];

    /**
     * Returns null when a needed conversion rate is missing so callers can fail
     * closed instead of accidentally allowing earnings past the package cap.
     */
    public function earnedTodayMinor(User $user, string $targetCurrency, ?Carbon $date = null): ?int
    {
        $date ??= now();
        $start = $date->copy()->startOfDay();
        $end = $start->copy()->addDay();
        $rows = DB::table('ledger_entries')
            ->join('wallets', 'wallets.id', '=', 'ledger_entries.wallet_id')
            ->where('wallets.user_id', $user->id)
            ->where('wallets.wallet_type', 'share')
            ->where('ledger_entries.entry_type', 'ad_share_reward')
            ->where('ledger_entries.direction', 'credit')
            ->where('ledger_entries.status', 'posted')
            ->whereNotNull('ledger_entries.posted_at')
            ->where('ledger_entries.posted_at', '>=', $start)
            ->where('ledger_entries.posted_at', '<', $end)
            ->groupBy('wallets.currency')
            ->selectRaw('wallets.currency as currency, SUM(ledger_entries.amount_minor) as amount_minor')
            ->get();

        return $this->convertRowsToMinor($rows, $targetCurrency);
    }

    /** Pending submissions reserve today's earning capacity until approved or rejected. */
    public function pendingMinor(User $user, string $targetCurrency): ?int
    {
        $rows = DB::table('ad_share_tasks')
            ->join('ads', 'ads.id', '=', 'ad_share_tasks.ad_id')
            ->where('ad_share_tasks.user_id', $user->id)
            ->where('ad_share_tasks.status', 'submitted')
            ->groupBy('ads.currency')
            ->selectRaw('ads.currency as currency, SUM(ad_share_tasks.reserved_reward_minor) as amount_minor')
            ->get();

        return $this->convertRowsToMinor($rows, $targetCurrency);
    }

    public function usedTodayMinor(User $user, string $targetCurrency, ?Carbon $date = null): ?int
    {
        $earned = $this->earnedTodayMinor($user, $targetCurrency, $date);
        $pending = $this->pendingMinor($user, $targetCurrency);
        if ($earned === null || $pending === null) return null;

        return $earned + $pending;
    }

    public function hasReachedLimit(User $user, SubscriptionPlan $plan, ?Carbon $date = null): ?bool
    {
        if ($plan->daily_revenue_minor === null) return false;

        $used = $this->usedTodayMinor($user, $plan->currency, $date);
        if ($used === null) return null;

        return $used >= (int) $plan->daily_revenue_minor;
    }

    private function convertRowsToMinor(iterable $rows, string $targetCurrency): ?int
    {
        $totalMinor = 0;
        $targetCurrency = strtoupper($targetCurrency);
        foreach ($rows as $row) {
            $sourceCurrency = strtoupper((string) $row->currency);
            $rate = $this->rate($sourceCurrency, $targetCurrency);
            if ($rate === null) return null;

            $majorAmount = CurrencyMinorUnits::fromMinor((int) $row->amount_minor, $sourceCurrency) * $rate;
            $totalMinor += CurrencyMinorUnits::toMinor($majorAmount, $targetCurrency);
        }

        return $totalMinor;
    }

    private function rate(string $from, string $to): ?float
    {
        if ($from === $to) return 1.0;
        $key = $from.'>'.$to;
        if (array_key_exists($key, $this->rateCache)) return $this->rateCache[$key];

        $direct = $this->latestRate($from, $to);
        if ($direct !== null) return $this->rateCache[$key] = $direct;

        $inverse = $this->latestRate($to, $from);
        if ($inverse !== null && $inverse > 0) return $this->rateCache[$key] = 1 / $inverse;

        if ($from !== 'USD' && $to !== 'USD') {
            $fromUsd = $this->rate($from, 'USD');
            $usdTo = $this->rate('USD', $to);
            if ($fromUsd !== null && $usdTo !== null) {
                return $this->rateCache[$key] = $fromUsd * $usdTo;
            }
        }

        return $this->rateCache[$key] = null;
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
