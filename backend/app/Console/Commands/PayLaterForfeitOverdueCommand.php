<?php

namespace App\Console\Commands;

use App\Services\PayLaterService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('paylater:forfeit-overdue')]
#[Description('Default unpaid Pay Later credits past their due date — revoke the granted minutes (and earnings).')]
class PayLaterForfeitOverdueCommand extends Command
{
    public function handle(PayLaterService $service): int
    {
        $count = $service->forfeitOverdue();

        $this->info("Forfeited {$count} overdue Pay Later credit(s).");

        return self::SUCCESS;
    }
}
