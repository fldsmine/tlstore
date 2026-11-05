<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('user_social_follows')) {
            return;
        }

        DB::table('user_social_follows')->whereNotNull('ledger_entry_id')->orderBy('id')->get(['user_id', 'social_handle_id'])
            ->each(function (object $follow): void {
                DB::table('transactions')
                    ->where('reference', 'SOCIAL-'.$follow->user_id.'-'.$follow->social_handle_id)
                    ->where('type', 'social_follow_reward')
                    ->delete();
            });
    }

    public function down(): void
    {
        // The ledger entry remains the canonical social reward transaction.
    }
};
