<?php

namespace App\Application\Actions\Wallets;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransferTopupFunds
{
    public function handle(User $user, int $amountMinor, string $targetType, string $idempotencyKey): void
    {
        if (! in_array($targetType, ['review', 'share', 'referral', 'bonus'], true)) {
            throw ValidationException::withMessages(['target_wallet' => 'Funds can only be transferred to review, share, or referral wallets.']);
        }

        DB::transaction(function () use ($user, $amountMinor, $targetType, $idempotencyKey): void {
            $hash = hash('sha256', $user->id.':'.$idempotencyKey);
            $debitKey = 'wallet-transfer:'.$hash.':debit';
            $creditKey = 'wallet-transfer:'.$hash.':credit';
            $existing = LedgerEntry::query()->where('idempotency_key', $debitKey)->with('wallet')->first();
            if ($existing) {
                $credit = LedgerEntry::query()->where('idempotency_key', $creditKey)->with('wallet')->first();
                if ($existing->amount_minor !== $amountMinor || ! $credit || $credit->wallet->wallet_type->value !== $targetType) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different transfer.']);
                }
                return;
            }

            $source = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'topup_fund')->where('status', 'active')->lockForUpdate()->firstOrFail();
            $target = Wallet::query()->where('user_id', $user->id)->where('wallet_type', $targetType)
                ->where('currency', $source->currency)->where('status', 'active')->lockForUpdate()->first();
            if (! $target) {
                $target = Wallet::query()->create([
                    'user_id' => $user->id, 'wallet_type' => $targetType,
                    'currency' => $source->currency, 'status' => 'active',
                ]);
            }

            $credits = (int) $source->ledgerEntries()->where('direction', 'credit')->where('status', 'posted')->sum('amount_minor');
            $debits = (int) $source->ledgerEntries()->where('direction', 'debit')->whereIn('status', ['posted', 'reserved'])->sum('amount_minor');
            if ($credits - $debits < $amountMinor) {
                throw ValidationException::withMessages(['amount_minor' => 'The top-up wallet has insufficient available funds.']);
            }

            $now = now();
            LedgerEntry::query()->create([
                'wallet_id' => $source->id, 'entry_type' => 'wallet_transfer', 'direction' => 'debit',
                'amount_minor' => $amountMinor, 'status' => 'posted', 'source_type' => null,
                'idempotency_key' => $debitKey, 'description' => 'Transfer top-up funds to '.$targetType.' wallet',
                'available_at' => $now, 'posted_at' => $now,
            ]);
            LedgerEntry::query()->create([
                'wallet_id' => $target->id, 'entry_type' => 'wallet_transfer', 'direction' => 'credit',
                'amount_minor' => $amountMinor, 'status' => 'posted', 'source_type' => null,
                'idempotency_key' => $creditKey, 'description' => 'Transfer from top-up funds wallet',
                'available_at' => $now, 'posted_at' => $now,
            ]);
        });
    }
}
