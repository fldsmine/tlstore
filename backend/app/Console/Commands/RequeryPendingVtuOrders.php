<?php

namespace App\Console\Commands;

use App\Jobs\RequeryVtuOrderJob;
use App\Models\VtuOrder;
use Illuminate\Console\Command;

class RequeryPendingVtuOrders extends Command
{
    protected $signature = 'vtu:requery-pending {--minutes=2 : Only orders older than this many minutes} {--limit=200 : Max orders per run}';

    protected $description = 'Dispatch a unique requery job for each pending VTU order';

    public function handle(): int
    {
        $ids = VtuOrder::query()
            ->pending()
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('minutes')))
            ->orderBy('created_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id');

        $ids->each(fn (string $id) => RequeryVtuOrderJob::dispatch($id));

        $this->info("Dispatched {$ids->count()} VTU requery job(s).");

        return self::SUCCESS;
    }
}
