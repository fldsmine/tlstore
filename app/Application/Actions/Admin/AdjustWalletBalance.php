<?php

namespace App\Application\Actions\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for admin-initiated wallet adjustments.
 *
 * Used by both the admin REST API (UserManagementController::walletAdjustment)
 * and the Livewire backoffice (Users/ShowUser) so the two surfaces can never
 * diverge on money movement. Balance is never edited directly — every change is
 * a ledger entry whose posting is handled by LedgerEntryObserver (row locks,
 * balance projection, transactions projection).
 *
 * Behaviour contract (kept identical to the historical API implementation):
 *  - Wallet currency must equal the user's wallet currency (or preferred/USD).
 *  - Idempotent: the same admin + user + client idempotency key yields the same
 *    ledger entry (same 'admin-wallet:'.sha256 key derivation).
 *  - Debit requires sufficient balance.
 */
final class AdjustWalletBalance
{
    public const WALLET_TYPES = ['review', 'review_group_b', 'review_group_c', 'share', 'referral', 'topup_fund', 'bonus'];

    /**
     * @return LedgerEntry the (idempotently) created ledger entry, with wallet loaded
     */
    public function handle(Admin $admin, User $user, string $walletType, string $currency, string $direction, int $amountMinor, string $reason, string $idempotencyKey): LedgerEntry
    {
        if ($user->trashed()) {
            throw ValidationException::withMessages(['user' => 'A deleted account cannot have wallet adjustments.']);
        }
        if (! in_array($walletType, self::WALLET_TYPES, true)) {
            throw ValidationException::withMessages(['wallet_type' => 'Unsupported wallet type.']);
        }
        $currency = strtoupper($currency);
        $walletCurrency = strtoupper($user->wallet_currency ?: $user->preferred_currency ?: 'USD');
        if ($currency !== $walletCurrency) {
            throw ValidationException::withMessages([
                'currency' => "All wallet adjustments must use this user's wallet currency ({$walletCurrency}).",
            ]);
        }
        if (! in_array($direction, ['credit', 'debit'], true)) {
            throw ValidationException::withMessages(['direction' => 'Direction must be credit or debit.']);
        }
        if ($amountMinor < 1 || $amountMinor > 100000000000) {
            throw ValidationException::withMessages(['amount_minor' => 'Amount must be a positive integer of minor units.']);
        }
        if (mb_strlen(trim($reason)) < 4) {
            throw ValidationException::withMessages(['reason' => 'A reason of at least 4 characters is required.']);
        }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 80) {
            throw ValidationException::withMessages(['idempotency_key' => 'An idempotency key of up to 80 characters is required.']);
        }

        $key = 'admin-wallet:'.hash('sha256', $admin->id.'|'.$user->id.'|'.$idempotencyKey);

        return DB::transaction(function () use ($admin, $user, $walletType, $currency, $direction, $amountMinor, $reason, $key): LedgerEntry {
            $existing = LedgerEntry::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing->load('wallet');
            }

            $wallet = Wallet::query()->firstOrCreate([
                'user_id' => $user->id,
                'wallet_type' => $walletType,
                'currency' => $currency,
            ], ['status' => 'active']);
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            if ($direction === 'debit' && (int) $wallet->balance_minor < $amountMinor) {
                throw ValidationException::withMessages(['amount_minor' => 'The wallet does not have enough balance for this debit.']);
            }

            $entry = LedgerEntry::query()->create([
                'wallet_id' => $wallet->id,
                'entry_type' => 'admin_adjustment',
                'direction' => $direction,
                'amount_minor' => $amountMinor,
                'status' => 'posted',
                'source_type' => 'user',
                'source_id' => $user->id,
                'idempotency_key' => $key,
                'description' => $reason,
                'available_at' => now(),
                'posted_at' => now(),
            ]);
            AuditLog::query()->create([
                'actor_admin_id' => $admin->id,
                'action' => 'user.wallet_adjusted',
                'entity_type' => 'user',
                'entity_id' => (string) $user->id,
                'after_values' => ['wallet_id' => $wallet->id, 'wallet_type' => $walletType, 'currency' => $currency, 'direction' => $direction, 'amount_minor' => $amountMinor, 'reason' => $reason, 'ledger_entry_id' => $entry->id],
                'created_at' => now(),
            ]);

            return $entry->load('wallet');
        });
    }
}
