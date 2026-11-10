<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Credits random amounts to existing user wallets and records both sides of the
 * movement:
 *
 *   - ledger_entries  the canonical money movement (what WalletBalanceSyncSeeder
 *                     recomputes balance_minor from);
 *   - transactions    the feed the app lists on the dashboard / history screen.
 *
 * The wallet balance is incremented in the same transaction, so the ledger, the
 * wallet and the feed stay consistent.
 *
 * Safe to re-run: every credit is keyed on a deterministic idempotency key and
 * transaction reference, so a second run skips rows it already wrote.
 *
 * Demo only - it writes money. The environment guard keeps it out of production;
 * remove that guard deliberately if you want to seed real balances.
 */
class WalletCreditSeeder extends Seeder
{
    /** How many credits to write per eligible wallet. */
    private const CREDITS_PER_WALLET = 2;

    /** Random credit range, in minor units (kobo / cents). 500 = 5.00. */
    private const MIN_CREDIT_MINOR = 1250000;
    private const MAX_CREDIT_MINOR = 2500000;

    /** Wallets that may be credited. topup_fund is excluded: it holds deposits. */
    private const WALLET_TYPES = ['topup_fund']; //['review', 'share', 'referral', 'bonus'];

    /**
     * transactions.type. Keep this aligned with a case value of
     * App\Enums\TransactionType - the model casts the column to that enum, so an
     * unknown value will fail when the row is read through Eloquent.
     */
    private const TRANSACTION_TYPE = 'top_up_fund';

    private const ENTRY_TYPE = 'demo_credit';

    /** Credits are spread over this many days so the feed looks lived-in. */
    private const BACKDATE_DAYS = 30;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->command?->warn('WalletCreditSeeder is demo-only; skipped outside local/testing/staging.');

            return;
        }

        $wallets = DB::table('wallets')
            ->whereIn('wallet_type', self::WALLET_TYPES)
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'user_id', 'wallet_type', 'currency']);

        if ($wallets->isEmpty()) {
            $this->command?->warn('WalletCreditSeeder: no eligible wallets found - run DemoUserSeeder first.');

            return;
        }

        $credited = 0;

        foreach ($wallets as $wallet) {
            for ($sequence = 1; $sequence <= self::CREDITS_PER_WALLET; $sequence++) {
                if ($this->credit($wallet, $sequence)) {
                    $credited++;
                }
            }
        }

        $this->command?->info(sprintf(
            'WalletCreditSeeder: %d credit(s) written across %d wallet(s).',
            $credited,
            $wallets->count(),
        ));
    }

    /**
     * Writes one credit + its transaction. Returns false when it was already seeded.
     */
    private function credit(object $wallet, int $sequence): bool
    {
        $key = sprintf(
            'seed-credit:u%d:%s:%s:%d',
            $wallet->user_id,
            $wallet->wallet_type,
            $wallet->currency,
            $sequence,
        );

        if (DB::table('ledger_entries')->where('idempotency_key', $key)->exists()) {
            return false;
        }

        $amountMinor = random_int(self::MIN_CREDIT_MINOR, self::MAX_CREDIT_MINOR);

        // transactions.amount is decimal(36,6) in major units.
        $amount = number_format($amountMinor / 100, 2, '.', '');

        $reference = sprintf(
            'CREDIT-%d-%s-%s-%d',
            $wallet->user_id,
            strtoupper($wallet->wallet_type),
            $wallet->currency,
            $sequence,
        );

        $postedAt = now()
            ->subDays(random_int(0, self::BACKDATE_DAYS))
            ->subMinutes(random_int(0, 1439))
            ->startOfSecond();

        $description = sprintf('Demo credit to %s wallet', $wallet->wallet_type);

        DB::transaction(function () use ($wallet, $key, $amountMinor, $amount, $reference, $postedAt, $description): void {
            $ledgerId = DB::table('ledger_entries')->insertGetId([
                'wallet_id' => $wallet->id,
                'entry_type' => self::ENTRY_TYPE,
                'direction' => 'credit',
                'amount_minor' => $amountMinor,
                'status' => 'posted',
                'source_type' => 'seeder',
                'source_id' => $wallet->user_id,
                'idempotency_key' => $key,
                'description' => $description,
                'available_at' => $postedAt,
                'posted_at' => $postedAt,
                'created_at' => $postedAt,
                'updated_at' => $postedAt,
            ]);

            DB::table('wallets')->where('id', $wallet->id)->update([
                'balance_minor' => DB::raw('balance_minor + '.(int) $amountMinor),
                'updated_at' => now(),
            ]);

            DB::table('transactions')->insert([
                'user_id' => $wallet->user_id,
                'type' => self::TRANSACTION_TYPE,
                'direction' => 'credit',
                'amount' => $amount,
                'fee' => '0.00',
                'wallet' => $wallet->wallet_type,
                'status' => 'completed',
                'reference' => $reference,
                'description' => $description,
                'meta' => json_encode([
                    'seeded' => true,
                    'wallet_type' => $wallet->wallet_type,
                    'currency' => $wallet->currency,
                    'ledger_entry_id' => $ledgerId,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $postedAt,
                'updated_at' => $postedAt,
            ]);
        });

        return true;
    }
}
