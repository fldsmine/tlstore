<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('system:prune')]
#[Description('Delete stale failed jobs and Telescope debug entries. Business + financial history is never pruned.')]
class SystemPruneCommand extends Command
{
    private const int FAILED_JOB_DAYS = 30;

    private const int TELESCOPE_DAYS = 7;

    public function handle(): int
    {
        $failed = DB::table('failed_jobs')->where('failed_at', '<', now()->subDays(self::FAILED_JOB_DAYS))->delete();
        $this->line('Failed jobs: pruned '.$failed.' record(s) older than '.self::FAILED_JOB_DAYS.' day(s).');

        try {
            $this->call('telescope:prune', ['--hours' => self::TELESCOPE_DAYS * 24]);
        } catch (Throwable) {
        }

        $this->info('system:prune complete.');

        return self::SUCCESS;
    }
}
