<?php

namespace App\Console\Commands;

use App\Models\AdShare;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

class PruneAdSharesCommand extends Command
{
    protected $signature = 'ads:prune-shares';

    protected $description = 'Reset the weekly share-to-earn guard by pruning ad shares from before this week';

    public function handle(): int
    {
        $count = AdShare::query()
            ->where('shared_at', '<', now()->startOfWeek(CarbonInterface::SUNDAY))
            ->delete();

        $this->info("Pruned {$count} ad share(s).");

        return self::SUCCESS;
    }
}
