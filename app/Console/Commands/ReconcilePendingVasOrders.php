<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileVasOrderJob;
use App\Features\Vas\Infrastructure\VtpassClient;
use App\Models\VtuOrder;
use Illuminate\Console\Command;

final class ReconcilePendingVasOrders extends Command
{
    protected $signature = 'vas:reconcile-pending {--minutes=2 : Only orders older than this many minutes} {--limit=200 : Maximum orders to dispatch}';

    protected $description = 'Dispatch requery jobs for pending VTpass orders';

    public function handle(VtpassClient $vtpass): int
    {
        if (! $vtpass->configured()) {
            $this->warn('VTpass is not configured; pending VAS orders were not dispatched.');

            return self::SUCCESS;
        }

        $minutes = max(0, (int) $this->option('minutes'));
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $ids = VtuOrder::query()
            ->where('provider', 'vtpass')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id');

        $ids->each(fn (int $id) => ReconcileVasOrderJob::dispatch($id));
        $this->info("Dispatched {$ids->count()} pending VAS order requery job(s).");

        return self::SUCCESS;
    }
}
