<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes wallets.balance_minor from ledger_entries.
 *
 * Legacy migration 2026_11_09_130000 added the balance_minor column and
 * back-filled it with (posted credits) - (posted + reserved debits). That
 * migration no longer exists - the column is part of the wallets create - so
 * the balance is never populated when demo activity is seeded. The demo
 * seeders write ledger entries but not wallet balances, which would leave every
 * dashboard at zero.
 *
 * Run this after any seeder that writes ledger entries. It is idempotent.
 */
class WalletBalanceSyncSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('wallets')->orderBy('id')->chunkById(500, function ($wallets): void {
            foreach ($wallets as $wallet) {
                $credits = (int) DB::table('ledger_entries')
                    ->where('wallet_id', $wallet->id)
                    ->where('direction', 'credit')
                    ->where('status', 'posted')
                    ->sum('amount_minor');

                $debits = (int) DB::table('ledger_entries')
                    ->where('wallet_id', $wallet->id)
                    ->where('direction', 'debit')
                    ->whereIn('status', ['posted', 'reserved'])
                    ->sum('amount_minor');

                $balance = max(0, $credits - $debits);

                if ((int) $wallet->balance_minor !== $balance) {
                    DB::table('wallets')->where('id', $wallet->id)->update([
                        'balance_minor' => $balance,
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
