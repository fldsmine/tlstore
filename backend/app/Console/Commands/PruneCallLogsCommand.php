<?php

namespace App\Console\Commands;

use App\Models\CallRecord;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('calls:prune')]
#[Description('Delete previous-day call records — claim same-day or lose it (claimed earnings are already credited).')]
class PruneCallLogsCommand extends Command
{
    public function handle(): int
    {
        $deleted = CallRecord::query()
            ->where('created_at', '<', now()->startOfDay())
            ->delete();

        $this->info("Pruned {$deleted} previous-day call record(s).");

        return self::SUCCESS;
    }
}
