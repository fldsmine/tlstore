<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backoffice Reports runs global (no user_id) time-windowed aggregates over these two large
     * tables. Every existing index is user_id-prefixed, so those queries full-scan millions of rows
     * even for a 7-day window (EXPLAIN: type=ALL, key=NULL). These indexes let the window filter
     * range-scan instead.
     */
    public function up(): void
    {
        if (! Schema::hasIndex('transactions', 'transactions_created_at_index')) {
            Schema::table('transactions', fn (Blueprint $table) => $table->index('created_at'));
        }
        if (! Schema::hasIndex('transactions', 'transactions_type_created_at_index')) {
            Schema::table('transactions', fn (Blueprint $table) => $table->index(['type', 'created_at']));
        }
        if (! Schema::hasIndex('call_claims', 'call_claims_claimed_at_index')) {
            Schema::table('call_claims', fn (Blueprint $table) => $table->index('claimed_at'));
        }
        if (! Schema::hasIndex('call_claims', 'call_claims_created_at_index')) {
            Schema::table('call_claims', fn (Blueprint $table) => $table->index('created_at'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('transactions', 'transactions_created_at_index')) {
            Schema::table('transactions', fn (Blueprint $table) => $table->dropIndex('transactions_created_at_index'));
        }
        if (Schema::hasIndex('transactions', 'transactions_type_created_at_index')) {
            Schema::table('transactions', fn (Blueprint $table) => $table->dropIndex('transactions_type_created_at_index'));
        }
        if (Schema::hasIndex('call_claims', 'call_claims_claimed_at_index')) {
            Schema::table('call_claims', fn (Blueprint $table) => $table->dropIndex('call_claims_claimed_at_index'));
        }
        if (Schema::hasIndex('call_claims', 'call_claims_created_at_index')) {
            Schema::table('call_claims', fn (Blueprint $table) => $table->dropIndex('call_claims_created_at_index'));
        }
    }
};
