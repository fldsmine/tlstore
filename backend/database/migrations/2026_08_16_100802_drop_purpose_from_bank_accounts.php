<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The conversational-chat refactor removed the in-app purchase flow, so the optional vendor
     * 'code' account (where customers paid when buying tokens in chat) is obsolete. Collapse back to
     * one bank account per user: delete every non-payout account, drop the purpose column, and
     * restore the unique(user_id) rule. On MySQL the (user_id, purpose) unique index backs the
     * user_id foreign key, so the FK is dropped before the index and re-added afterwards.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('bank_accounts', 'purpose')) {
            return;
        }

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'purpose']);
        });

        DB::table('bank_accounts')->where('purpose', '!=', 'payout')->delete();

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn('purpose');
        });
    }

    public function down(): void
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
};
