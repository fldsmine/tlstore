<?php

namespace App\Console\Commands;

use App\Services\ReportRollupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reports:rollup {--days=3 : Recompute the most recent N days (incremental)} {--all : Backfill every day from the earliest record}')]
#[Description('Pre-aggregate the heavy Backoffice Reports metrics into report_daily_stats. Past days are immutable; the report computes today live, so figures stay exact and real-time.')]
class RollupReportsCommand extends Command
{
    public function handle(ReportRollupService $rollups): int
    {
        if ($this->option('all')) {
            $from = $rollups->earliestDay();
            $this->info("Backfilling rollups from {$from->toDateString()} to today…");
            $count = $rollups->rollupRange($from, today());
            $this->info("Rolled up {$count} day(s).");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $from = today()->subDays($days - 1);
        $count = $rollups->rollupRange($from, today());
        $this->info("Rolled up the most recent {$count} day(s).");

        return self::SUCCESS;
    }
}
