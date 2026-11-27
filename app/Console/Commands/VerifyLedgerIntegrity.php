<?php

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only proof of the Phase 2 invariants:
 *
 *  1. `wallets.balance_minor` equals the sum of its ledger entries' impacts.
 *  2. every `transactions.status` is part of the canonical vocabulary.
 *  3. no terminal payment intent is still projected as `pending`.
 *
 * Run it after a release (or from a scheduler alert) to catch drift before a
 * user does. Exits non-zero when a discrepancy exists so CI/cron can fail.
 */
class VerifyLedgerIntegrity extends Command
{
    protected $signature = 'ledger:verify {--json : Emit machine-readable output}';

    protected $description = 'Verify wallet balances, transaction statuses and payment-intent projections against the ledger';

    public function handle(): int
    {
        $balanceDrift = $this->balanceDrift();
        $invalidStatuses = $this->invalidTransactionStatuses();
        $danglingIntents = $this->danglingProjections();

        $ok = $balanceDrift === [] && $invalidStatuses === 0 && $danglingIntents === 0;

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => $ok,
                'wallet_balance_drift' => $balanceDrift,
                'invalid_transaction_statuses' => $invalidStatuses,
                'dangling_payment_intents' => $danglingIntents,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Ledger integrity check');
        $this->line(sprintf('  wallets checked:        %d', Wallet::query()->count()));
        $this->line(sprintf('  balance discrepancies:  %d', count($balanceDrift)));
        $this->line(sprintf('  non-canonical statuses: %d', $invalidStatuses));
        $this->line(sprintf('  dangling intents:       %d', $danglingIntents));

        foreach (array_slice($balanceDrift, 0, 20) as $row) {
            $this->warn(sprintf(
                '  wallet %d: stored %d, ledger %d (delta %d)',
                $row['wallet_id'], $row['stored_minor'], $row['ledger_minor'], $row['delta_minor'],
            ));
        }

        $ok
            ? $this->info('Ledger is consistent.')
            : $this->error('Ledger discrepancies found.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<int, array{wallet_id:int, stored_minor:int, ledger_minor:int, delta_minor:int}>
     */
    private function balanceDrift(): array
    {
        $impact = "COALESCE(SUM(CASE
            WHEN direction = 'credit' AND status = 'posted' THEN amount_minor
            WHEN direction = 'debit' AND status IN ('posted', 'reserved') THEN -amount_minor
            ELSE 0 END), 0)";

        $ledgerTotals = DB::table('ledger_entries')
            ->selectRaw("wallet_id, {$impact} as ledger_total")
            ->whereNotNull('wallet_id')
            ->groupBy('wallet_id')
            ->pluck('ledger_total', 'wallet_id');

        $drift = [];

        Wallet::query()->orderBy('id')->chunkById(500, function ($wallets) use ($ledgerTotals, &$drift): void {
            foreach ($wallets as $wallet) {
                $stored = (int) $wallet->balance_minor;
                $expected = (int) ($ledgerTotals[$wallet->id] ?? 0);

                if ($stored !== $expected) {
                    $drift[] = [
                        'wallet_id' => (int) $wallet->id,
                        'stored_minor' => $stored,
                        'ledger_minor' => $expected,
                        'delta_minor' => $stored - $expected,
                    ];
                }
            }
        });

        return $drift;
    }

    private function invalidTransactionStatuses(): int
    {
        return Transaction::query()
            ->whereNotIn('status', array_column(TransactionStatus::cases(), 'value'))
            ->count();
    }

    private function danglingProjections(): int
    {
        $terminal = [
            TransactionStatus::Failed->value,
            TransactionStatus::Cancelled->value,
            TransactionStatus::Expired->value,
        ];

        $dangling = 0;

        // Portable on purpose: no string concatenation in SQL (MySQL/Postgres/
        // SQLite all disagree), so the reference is built in PHP.
        DB::table('payment_intents')
            ->whereIn('status', $terminal)
            ->orderBy('id')
            ->chunk(500, function ($intents) use (&$dangling): void {
                $references = [];
                foreach ($intents as $intent) {
                    $references[] = 'payment-intent:'.$intent->id;
                }

                $dangling += Transaction::query()
                    ->whereIn('reference', $references)
                    ->where('status', TransactionStatus::Pending->value)
                    ->count();
            });

        return $dangling;
    }
}
