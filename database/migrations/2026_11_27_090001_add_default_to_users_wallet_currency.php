<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `2026_11_12_000001_add_wallet_currency_to_users` made the column NOT NULL
 * without a database-level default, so any insert that omitted the attribute
 * (the user factory, several feature tests, raw `DB::table('users')->insert()`
 * calls) failed with an integrity-constraint violation.
 *
 * The application also defaults the attribute in `User::booted()`, which keeps
 * values aligned with the user's preferred currency; this default is the
 * last-resort safety net for inserts that bypass Eloquent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('wallet_currency', 3)->default('NGN')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('wallet_currency', 3)->default(null)->nullable(false)->change();
        });
    }
};
