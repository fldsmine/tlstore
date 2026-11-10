<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ledger_entries
 *
 * Double-entry style money movements against a wallet (the canonical reward record).
 *
 * Relationships:
 *   - wallet_id -> wallets.id (restrict delete)
 *   - referenced by referral_rewards and user_social_follows (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 40);
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 24)->default('posted');
            $table->string('source_type', 48)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'status', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
