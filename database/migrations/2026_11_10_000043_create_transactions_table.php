<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * transactions
 *
 * User-facing transaction feed (the projection the app lists); one row per ledger movement.
 *
 * Relationships:
 *   - user_id -> users.id (cascade delete)
 *   - wallet column stores a wallet_type key, not a FK
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->comment('App\Enums\TransactionType');
            $table->string('direction')->comment('credit | debit');
            $table->decimal('amount', 36, 6)->default(0)->comment('Always positive; direction decides the sign');
            $table->decimal('fee', 36, 6)->default(0)->comment('Processing fee; net = amount - fee');
            $table->string('wallet')->nullable()->comment('Wallet type that was affected');
            $table->string('status')->default('completed')->comment('completed | pending | failed');
            $table->string('reference')->unique();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'direction', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
