<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CallClaim;
use App\Models\ReportDailyStat;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pre-aggregates the Backoffice Reports metrics that scan the big tables (transactions, call_claims,
 * users) into report_daily_stats — one row per (day, metric, bucket). The report reads immutable
 * PAST days from the rollup and computes TODAY live, so it stays exact and real-time while avoiding
 * multi-million-row scans. Each metric is defined ONCE here (computeDaily) and used both to persist
 * past days and to compute the live "today" head.
 */
class ReportRollupService
{
    /** @var list<string> */
    public const array METRICS = [
        'signups',
        'earnings_by_type',
        'cloud_earnings',
        'referral_bonus',
        'adjustments',
        'agent_transfer',
        'call_earnings',
        'calls_claimed',
    ];

    /**
     * Per-day, per-bucket aggregates for one metric over [$from, $to).
     *
     * @return array<string, array<string, array{cnt: int, amt: float}>> day => bucket => {cnt, amt}
     */
    public function computeDaily(string $metric, CarbonInterface $from, CarbonInterface $to): array
    {
        $spendable = User::SPENDABLE_WALLETS;

        return match ($metric) {
            'signups' => $this->groupDaily(User::query(), 'created_at', $from, $to, null, '0'),
            'earnings_by_type' => $this->groupDaily(
                Transaction::query()->where('direction', 'credit')->where('status', 'completed')
                    ->where('type', '!=', TransactionType::ADJUSTMENT->value)->whereIn('wallet', $spendable),
                'created_at', $from, $to, 'type', 'coalesce(sum(amount), 0)'
            ),
            'cloud_earnings' => $this->groupDaily(
                Transaction::query()->where('type', TransactionType::CLOUD_EARNING->value)
                    ->where('direction', 'credit')->where('status', 'completed'),
                'created_at', $from, $to, 'wallet', 'coalesce(sum(amount), 0)'
            ),
            'adjustments' => $this->groupDaily(
                Transaction::query()->where('type', TransactionType::ADJUSTMENT->value)->where('status', 'completed'),
                'created_at', $from, $to, 'direction', 'coalesce(sum(amount), 0)'
            ),
            'agent_transfer' => $this->groupDaily(
                Transaction::query()->where('type', TransactionType::AGENT_TRANSFER->value)->where('direction', 'credit'),
                'created_at', $from, $to, null, 'coalesce(sum(amount), 0)'
            ),
            'call_earnings' => $this->groupDaily(CallClaim::query(), 'claimed_at', $from, $to, 'call_type_id', 'coalesce(sum(amount), 0)'),
            'calls_claimed' => $this->groupDaily(CallClaim::query(), 'created_at', $from, $to, null, '0'),
            'referral_bonus' => $this->referralBonusDaily($from, $to),
            default => [],
        };
    }

    /**
     * Referral bonuses split by meta->level (direct / indirect) — kept as two portable JSON `where`
     * passes rather than grouping by a JSON path (SQLite/MySQL portability).
     *
     * @return array<string, array<string, array{cnt: int, amt: float}>>
     */
    private function referralBonusDaily(CarbonInterface $from, CarbonInterface $to): array
    {
        $out = [];

        foreach (['direct', 'indirect'] as $level) {
            $rows = $this->groupDaily(
                Transaction::query()->where('type', TransactionType::REFERRAL_BONUS->value)
                    ->where('status', 'completed')->where('meta->level', $level),
                'created_at', $from, $to, null, 'coalesce(sum(amount), 0)'
            );

            foreach ($rows as $day => $buckets) {
                $out[$day][$level] = $buckets[''];
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<string, array{cnt: int, amt: float}>>
     */
    private function groupDaily(Builder $query, string $dateColumn, CarbonInterface $from, CarbonInterface $to, ?string $bucketColumn, string $valueExpr): array
    {
        $select = "date({$dateColumn}) as d, count(*) as cnt, {$valueExpr} as amt";
        $groupBy = ['d'];

        if ($bucketColumn !== null) {
            $select .= ", {$bucketColumn} as b";
            $groupBy[] = 'b';
        }

        $rows = $query
            ->where($dateColumn, '>=', $from)
            ->where($dateColumn, '<', $to)
            ->selectRaw($select)
            ->groupBy($groupBy)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $day = substr((string) $row->d, 0, 10);
            $bucket = $bucketColumn !== null ? (string) ($row->b ?? '') : '';
            $out[$day][$bucket] = ['cnt' => (int) $row->cnt, 'amt' => (float) $row->amt];
        }

        return $out;
    }

    /** Recompute + persist every metric for a single (past) day. Idempotent. */
    public function rollupDay(CarbonInterface $day): void
    {
        $from = $day->copy()->startOfDay();
        $to = $from->copy()->addDay();
        $dayKey = $from->toDateString();

        DB::transaction(function () use ($from, $to, $dayKey): void {
            foreach (self::METRICS as $metric) {
                ReportDailyStat::query()->where('metric', $metric)->where('day', $dayKey)->delete();

                $rows = [];
                foreach ($this->computeDaily($metric, $from, $to) as $buckets) {
                    foreach ($buckets as $bucket => $value) {
                        $rows[] = [
                            'day' => $dayKey,
                            'metric' => $metric,
                            'bucket' => (string) $bucket,
                            'cnt' => $value['cnt'],
                            'amount' => $value['amt'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if ($rows !== []) {
                    ReportDailyStat::query()->insert($rows);
                }
            }
        });
    }

    /** Rollup an inclusive day range (oldest → newest). */
    public function rollupRange(CarbonInterface $from, CarbonInterface $to): int
    {
        $count = 0;
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $this->rollupDay($day);
            $count++;
        }

        return $count;
    }

    /** Earliest day that has any source data (for a full backfill). */
    public function earliestDay(): CarbonInterface
    {
        $dates = array_filter([
            User::query()->min('created_at'),
            Transaction::query()->min('created_at'),
            CallClaim::query()->min('created_at'),
        ]);

        return $dates === [] ? today()->startOfDay() : Carbon::parse(min($dates))->startOfDay();
    }

    /**
     * Daily buckets for a metric over [$start, today]: immutable past days from the rollup + TODAY
     * computed live (real-time head).
     *
     * @return array<string, array<string, array{cnt: int, amt: float}>>
     */
    public function dailySeries(string $metric, CarbonInterface $start): array
    {
        $todayKey = today()->toDateString();

        $out = [];
        ReportDailyStat::query()
            ->where('metric', $metric)
            ->where('day', '>=', $start->toDateString())
            ->where('day', '<', $todayKey)
            ->get(['day', 'bucket', 'cnt', 'amount'])
            ->each(function (ReportDailyStat $r) use (&$out): void {
                $out[$r->day->toDateString()][(string) $r->bucket] = ['cnt' => (int) $r->cnt, 'amt' => (float) $r->amount];
            });

        $todayStart = today()->startOfDay();
        foreach ($this->computeDaily($metric, $todayStart, $todayStart->copy()->addDay()) as $day => $buckets) {
            $out[$day] = $buckets;
        }

        return $out;
    }

    /**
     * Per-day total of one field ('cnt'|'amt'), summed across buckets.
     *
     * @return array<string, float>
     */
    public function dailyTotals(string $metric, CarbonInterface $start, string $field): array
    {
        $out = [];
        foreach ($this->dailySeries($metric, $start) as $day => $buckets) {
            $out[$day] = array_sum(array_map(fn (array $v): float => (float) $v[$field], $buckets));
        }

        return $out;
    }

    /**
     * Window totals of one field per bucket.
     *
     * @return array<string, float>
     */
    public function bucketTotals(string $metric, CarbonInterface $start, string $field): array
    {
        $out = [];
        foreach ($this->dailySeries($metric, $start) as $buckets) {
            foreach ($buckets as $bucket => $v) {
                $out[$bucket] = ($out[$bucket] ?? 0) + (float) $v[$field];
            }
        }

        return $out;
    }

    public function total(string $metric, CarbonInterface $start, string $field): float
    {
        return array_sum($this->dailyTotals($metric, $start, $field));
    }
}
