<?php

namespace App\Console\Commands;

use App\Services\CallLogService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('calls:generate')]
#[Description('Generate today\'s shared call pool (the pool also generates lazily on first view).')]
class GenerateCallsCommand extends Command
{
    public function handle(CallLogService $service): int
    {
        $count = $service->ensureTodaysPool();

        if ($count > 0) {
            $this->info("Generated {$count} pool call(s) for today.");
        } elseif ($service->poolExistsToday()) {
            $this->info("Today's call pool already exists.");
        } else {
            $this->warn('No call-pool records were generated; verify active CallTypes and their pool/share settings.');
        }

        return self::SUCCESS;
    }
}
