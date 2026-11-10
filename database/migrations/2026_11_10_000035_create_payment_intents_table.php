<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_intents
 *
 * A single attempt to collect money (subscription, wallet top-up, ...).
 *
 * Relationships:
 *   - user_id -> users.id (cascade delete)
 *   - has many payment_transactions
 *   - referenced by user_subscriptions and manual_deposit_submissions (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('source_type', 48)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('provider', 32);
            $table->string('provider_reference', 191)->nullable()->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->string('status', 32)->default('pending');
            $table->json('provider_metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
