<?php

namespace App\Console\Commands;

use App\Application\Actions\Rankings\RewardDueRanks;
use Illuminate\Console\Command;

final class RewardDueUserRanks extends Command
{
    protected $signature = 'rankings:reward-monthly';
    protected $description = 'Credit monthly bonus wallet rewards for due user ranking qualifications';

    public function handle(RewardDueRanks $rewards): int
    {
        $count = $rewards->handle();
        $this->info("Issued {$count} monthly ranking reward(s).");
        return self::SUCCESS;
    }
}
