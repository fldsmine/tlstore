<?php

namespace App\Application\Actions\Vas;

use App\Enums\LedgerEntryStatus;
use App\Enums\VtuOrderStatus;
use App\Models\VtuOrder;
use Illuminate\Support\Facades\DB;

/** Applies an authoritative VTpass outcome once, regardless of whether it came from pay or requery. */
final class SettleVasOrder
{
    /** @param array{reached:bool,status:string,external_id:?string,token:?string,code:?string,message:string} $result */
    public function handle(VtuOrder $order, array $result): VtuOrder
    {
        if (! $result['reached']) {
            return $order->refresh();
        }

        return DB::transaction(function () use ($order, $result): VtuOrder {
            $locked = VtuOrder::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->status->isPending()) {
                return $locked ?? $order;
            }

            $locked->external_id = $result['external_id'] ?? $locked->external_id;
            $locked->token = $result['token'] ?? $locked->token;
            $locked->meta = array_merge((array) $locked->meta, [
                'vtpass' => array_filter([
                    'code' => $result['code'] ?? null,
                    'message' => $result['message'] ?? null,
                    'last_checked_at' => now()->toISOString(),
                ], static fn ($value): bool => $value !== null && $value !== ''),
            ]);

            $status = VtuOrderStatus::from((string) $result['status']);
            $locked->status = $status;
            if ($status->isTerminal()) {
                $locked->settled_at = now();
            }

            if (in_array($status, [VtuOrderStatus::Failed, VtuOrderStatus::Reversed], true)) {
                $locked->refunded = true;
                $debit = $locked->debitLedgerEntry;
                if ($debit && $debit->status === LedgerEntryStatus::Reserved) {
                    // Reversing the reserved debit restores the balance exactly once. The ledger
                    // observer also updates the user's transaction history projection.
                    $debit->status = LedgerEntryStatus::Reversed;
                    $debit->save();
                }
            } elseif ($status === VtuOrderStatus::Delivered) {
                $debit = $locked->debitLedgerEntry;
                if ($debit && $debit->status === LedgerEntryStatus::Reserved) {
                    $debit->status = LedgerEntryStatus::Posted;
                    $debit->save();
                }
            }

            $locked->save();

            return $locked->refresh();
        });
    }
}
