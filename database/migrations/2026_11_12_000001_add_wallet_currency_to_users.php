<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('wallet_currency', 3)->nullable()->after('preferred_currency');
        });

        DB::table('users')->whereNull('wallet_currency')->update([
            'wallet_currency' => DB::raw("UPPER(COALESCE(preferred_currency, 'USD'))"),
        ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->char('wallet_currency', 3)->nullable(false)->change();
        });

        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->after('description');
        });

        $now = now();
        $users = DB::table('users')->select('id', 'wallet_currency', 'preferred_currency')->get();
        foreach ($users as $user) {
            $currency = strtoupper($user->wallet_currency ?: $user->preferred_currency ?: 'USD');
            foreach (['review_group_b', 'review_group_c'] as $type) {
                DB::table('wallets')->insertOrIgnore([
                    'user_id' => $user->id,
                    'wallet_type' => $type,
                    'currency' => $currency,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('wallet_currency');
        });
    }
};
