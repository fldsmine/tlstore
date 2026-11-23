<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the two backoffice report pages that were timing out.
 *
 * Both pages aggregate over append-only tables that have grown large, and every window filter
 * (`read_at >= ?`, `used_at >= ?`, `shared_at >= ?`, …) was falling back to a full table scan
 * because the only indexes on those tables are per-user composites like (user_id, read_at) —
 * useful for one account's inbox, useless for "everyone who opened something today".
 *
 * The indexes are additive: nothing else changes, reads just stop scanning. Each one is guarded,
 * so re-running the migration is a no-op.
 *
 * NOTE for deployment: on a large table, adding an index rewrites the table. MySQL 8 does this
 * online (INPLACE) for secondary indexes, but the migration can still take a while — run it in a
 * low-traffic window. No data is modified.
 */
return new class extends Migration
{
    /**
     * table => list of column sets that the report queries filter or group by.
     *
     * @var array<string, list<list<string>>>
     */
    private array $indexes = [
        // Cloud Inbox opens — the whole-table "who opened" filters and delivery windows.
        // The last one also covers the reports page's "unclaimed SMS liability" sum
        // (type = sponsored, claimed_at is null → sum(earn_rate)) as an index-only scan.
        'cloud_inbox_messages' => [
            ['read_at'],
            ['claimed_at'],
            ['created_at'],
            ['type', 'claimed_at', 'earn_rate'],
        ],
        // Reports — payouts series, requested/pending counts.
        'withdrawals' => [
            ['status', 'processed_at'],
            ['created_at'],
        ],
        // Reports — ad-shares series and payouts.
        'ad_shares' => [['shared_at']],
        // Reports — referrals in the window.
        'referrals' => [['created_at']],
        // Reports — coupons redeemed / activations / upgrades in the window.
        'coupons' => [
            ['used_at'],
            ['type', 'used_at'],
        ],
        // Reports — top earners aggregates by type before joining users.
        'transactions' => [
            ['type', 'direction', 'user_id'],
        ],
        // Reports — earliest signup (window start), new agents in the window.
        'users' => [
            ['created_at'],
            ['is_agent', 'created_at'],
        ],
        // Reports — small window counts.
        'loans' => [['created_at']],
        'minute_top_ups' => [['created_at']],
        'reward_targets' => [['achieved_at']],
        'debit_cards' => [['created_at']],
        'cloud_calls' => [['status', 'ended_at']],
        'cloud_broadcasts' => [['created_at']],
        'cloud_distributions' => [['created_at']],
        'cloud_bonus_claims' => [['claimed_at']],
        'cloud_messages' => [['created_at']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($sets as $columns) {
                $this->addIndex($table, $columns);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($sets as $columns) {
                $this->dropIndex($table, $columns);
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndex(string $table, array $columns): void
    {
        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns));
        } catch (QueryException $e) {
            // Already there (MySQL: "Duplicate key name", SQLite/Postgres: "already exists").
            if (! $this->isDuplicate($e)) {
                throw $e;
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropIndex(string $table, array $columns): void
    {
        $name = $table.'_'.implode('_', $columns).'_index';

        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        } catch (QueryException) {
            // Never existed, or already dropped — down() stays idempotent.
        }
    }

    private function isDuplicate(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'duplicate') || str_contains($message, 'already exists');
    }
};
