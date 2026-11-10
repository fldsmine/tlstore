<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * review_tasks
 *
 * A review slot claimed by a reviewer for a campaign.
 *
 * Relationships:
 *   - app_review_campaign_id   -> app_review_campaigns.id (cascade delete)
 *   - reviewer_id              -> users.id (cascade delete)
 *   - review_template_version_id -> review_template_versions.id (restrict delete)
 *   - has one review_submission
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_review_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('review_template_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('reserved');
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedBigInteger('reserved_reward_minor');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['app_review_campaign_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_tasks');
    }
};
