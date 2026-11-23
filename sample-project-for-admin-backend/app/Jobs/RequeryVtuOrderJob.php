<?php

namespace App\Jobs;

use App\Models\VtuOrder;
use App\Services\Vas\VtuPurchaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Requery and settle a single pending VTU order against its pinned provider. Unique per order, so
 * a webhook and the sweep can never requery the same order at once (settlement is idempotent on
 * the order status either way).
 */
class RequeryVtuOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(public string $orderId) {}

    public function uniqueId(): string
    {
        return $this->orderId;
    }

    public function handle(VtuPurchaseService $service): void
    {
        $order = VtuOrder::find($this->orderId);

        if ($order !== null && $order->status->isPending()) {
            $service->requery($order);
        }
    }
}
