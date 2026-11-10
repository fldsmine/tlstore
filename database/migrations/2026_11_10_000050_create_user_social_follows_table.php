<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_social_follows
 *
 * A user's follow of an official channel and the reward it produced.
 *
 * Relationships:
 *   - user_id          -> users.id (cascade delete)
 *   - social_handle_id -> social_handles.id (restrict delete)
 *   - ledger_entry_id  -> ledger_entries.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_social_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_handle_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('completed');
            $table->unsignedBigInteger('reward_amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->foreignId('ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('followed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'social_handle_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_follows');
    }
};
