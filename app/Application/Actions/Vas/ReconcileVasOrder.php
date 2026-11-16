<?php

namespace App\Application\Actions\Vas;

use App\Features\Vas\Infrastructure\VtpassClient;
use App\Models\VtuOrder;

final class ReconcileVasOrder
{
    public function __construct(
        private readonly VtpassClient $vtpass,
        private readonly SettleVasOrder $settle,
    ) {}

    public function handle(VtuOrder $order): VtuOrder
    {
        if (! $order->status->isPending()) {
            return $order;
        }

        if ($order->provider !== 'vtpass') {
            // Orders are pinned to their provider. Never query an order through another driver.
            return $order;
        }

        if (! $this->vtpass->configured()) {
            return $order;
        }

        return $this->settle->handle($order, $this->vtpass->requery($order->request_id));
    }
}
