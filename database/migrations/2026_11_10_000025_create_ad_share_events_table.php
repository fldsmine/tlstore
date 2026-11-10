<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ad_share_events
 *
 * Evidence submitted for a share task (tracking token, channel, validation state).
 *
 * Relationships:
 *   - ad_share_task_id -> ad_share_tasks.id (cascade delete)
 *   - referenced by moderation_decisions and risk_signals
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_share_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_share_task_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 48);
            $table->string('tracking_token', 100)->unique();
            $table->string('external_reference', 191)->nullable();
            $table->string('status', 24)->default('pending_validation');
            $table->string('verification_method', 48)->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->index(['ad_share_task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_share_events');
    }
};
