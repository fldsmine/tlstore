<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ad_share_tasks
 *
 * A share slot reserved by a user for an ad campaign.
 *
 * Relationships:
 *   - ad_id   -> ads.id   (cascade delete)
 *   - user_id -> users.id (cascade delete)
 *   - has many ad_share_events
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_share_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('reserved');
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedBigInteger('reserved_reward_minor');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['ad_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_share_tasks');
    }
};
