<?php

namespace App\Console\Commands;

use App\Application\Actions\Rankings\CheckRankEligibility;
use Illuminate\Console\Command;

final class CheckUserRankEligibility extends Command
{
    protected $signature = 'rankings:check-eligibility {--user= : Check a single user ID}';
    protected $description = 'Find users who meet an active monthly ranking level';

    public function handle(CheckRankEligibility $checker): int
    {
        $user = $this->option('user') ? (int) $this->option('user') : null;
        $count = $checker->handle($user);
        $this->info("Created {$count} new ranking qualification(s).");
        return self::SUCCESS;
    }
}
