<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wallets
 *
 * One balance per user, wallet type (earning / referral / share / bonus) and currency. 
 *
 * Relationships:
 *   - user_id -> users.id (cascade delete)
 *   - has many ledger_entries, withdrawal_requests, referral_rewards
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wallet_type', 24);
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['user_id', 'wallet_type', 'currency'], 'wallet_user_type_currency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
