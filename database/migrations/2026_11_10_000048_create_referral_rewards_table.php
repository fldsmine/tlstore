<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * referral_rewards
 *
 * Rewards earned by a referrer, tied to the ledger entry that paid them.
 *
 * Relationships:
 *   - referral_attribution_id -> referral_attributions.id (cascade delete)
 *   - wallet_id               -> wallets.id (restrict delete)
 *   - ledger_entry_id         -> ledger_entries.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_attribution_id')->constrained()->cascadeOnDelete();
            $table->string('reward_type', 32)->default('subscription');
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('pending');
            $table->timestamps();
            $table->unique(['referral_attribution_id', 'reward_type', 'source_type', 'source_id'], 'referral_reward_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
