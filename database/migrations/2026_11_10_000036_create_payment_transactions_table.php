<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_transactions
 *
 * Provider-side charge/refund records belonging to a payment intent.
 *
 * Relationships:
 *   - payment_intent_id -> payment_intents.id (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->string('provider_transaction_id', 191)->nullable()->unique();
            $table->string('transaction_type', 24);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24);
            $table->json('provider_response')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['payment_intent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
