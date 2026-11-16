<?php

namespace App\Jobs;

use App\Application\Actions\Vas\ReconcileVasOrder;
use App\Models\VtuOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** One active provider requery per order; settlement is additionally guarded by an order lock. */
final class ReconcileVasOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(ReconcileVasOrder $reconcile): void
    {
        $order = VtuOrder::query()->find($this->orderId);
        if ($order?->status->isPending()) {
            $reconcile->handle($order);
        }
    }
}
