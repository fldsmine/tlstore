<?php

namespace App\Console\Commands;

use App\Services\AdService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ads:grow')]
#[Description('Progressively grow each active ad\'s figures + reward by its growth percent, on its own interval.')]
class GrowAdFiguresCommand extends Command
{
    public function handle(AdService $ads): int
    {
        $count = $ads->growFigures();

        $this->info("Grew {$count} active ad(s) this run.");

        return self::SUCCESS;
    }
}
