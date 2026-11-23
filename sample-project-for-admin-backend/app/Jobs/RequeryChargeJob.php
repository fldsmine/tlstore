<?php

namespace App\Jobs;

use App\Models\ProductOrder;
use App\Models\Transaction;
use App\Services\Payments\ChargeSettlement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Requery and settle a single pending gateway charge (bill top-up / website renewal) or a guest
 * eSIM order from the product pages. Unique per reference, so the sweep and the webhook can never
 * requery the same reference concurrently — on top of the already row-locked, idempotent settlement.
 */
class RequeryChargeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Release the uniqueness lock after 10 minutes even if the job never completes. */
    public int $uniqueFor = 600;

    public function __construct(public string $reference) {}

    public function uniqueId(): string
    {
        return $this->reference;
    }

    public function handle(ChargeSettlement $settlement): void
    {
        $status = $settlement->finalize($this->reference);

        // Only a definitive 'pending' means the gateway confirmed the charge is still unpaid;
        // 'unavailable' is a transport failure — never terminalize on it (the money may be in).
        if ($status !== 'pending') {
            return;
        }

        $transaction = Transaction::where('reference', $this->reference)->first();

        // A charge the gateway still calls pending a day after start has a dead checkout — fail it,
        // row-locked + re-checked so a completion racing in from the webhook is not clobbered.
        if ($transaction !== null && $transaction->created_at->lte(now()->subDay())) {
            DB::transaction(function () use ($transaction): void {
                $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->first();

                if ($locked !== null && $locked->status === 'pending') {
                    $locked->update(['status' => 'failed']);
                }
            });
        }

        // Same for a guest order from the product pages: a day-old unpaid checkout is dead, and
        // leaving it pending forever would keep it in the fulfilment sweep.
        $order = ProductOrder::where('reference', $this->reference)->first();

        if ($order !== null && $order->created_at->lte(now()->subDay())) {
            DB::transaction(function () use ($order): void {
                $locked = ProductOrder::whereKey($order->id)->lockForUpdate()->first();

                if ($locked !== null && $locked->status === ProductOrder::STATUS_PENDING) {
                    $locked->update([
                        'status' => ProductOrder::STATUS_FAILED,
                        'failure_reason' => 'Checkout expired without a completed payment.',
                    ]);
                }
            });
        }
    }
}
