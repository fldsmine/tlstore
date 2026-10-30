<?php

namespace App\Console\Commands;

use App\Services\TargetRewardService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rewards:settle')]
#[Description('Mark past, still-active daily reward targets as missed (the day ended without hitting them).')]
class SettleRewardTargetsCommand extends Command
{
    public function handle(TargetRewardService $rewards): int
    {
        $missed = $rewards->settleMissed();

        $this->info("Settled {$missed} unmet reward target(s) as missed.");

        return self::SUCCESS;
    }
}
