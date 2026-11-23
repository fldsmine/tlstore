<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user can now hold two bank accounts: their 'payout' account (withdrawals) and an optional
     * 'code' account (where customers pay when buying their tokens in chat). Replace the single-row
     * per-user rule (unique user_id) with one row per (user_id, purpose). Existing rows default to
     * 'payout', preserving every current account as the payout account.
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('purpose')->default('payout')->after('user_id');
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('bank_accounts_user_id_unique');
            $table->unique(['user_id', 'purpose']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'purpose']);
        });

        // Keep only the payout row per user so the unique(user_id) can be restored cleanly.
        DB::table('bank_accounts')->where('purpose', '!=', 'payout')->delete();

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn('purpose');
        });
    }
};
