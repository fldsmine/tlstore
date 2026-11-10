<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * app_review_campaigns
 *
 * Paid campaign that pays reviewers per approved review of an app.
 *
 * Relationships:
 *   - app_id   -> apps.id  (cascade delete)
 *   - owner_id -> users.id (null on delete)
 *   - has many review_tasks
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_review_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_key', 64)->unique();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('paid_amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('location', 32)->nullable()->comment('Country or region code; NULL is global');
            $table->unsignedBigInteger('review_reward_minor');
            $table->unsignedInteger('maximum_review')->nullable();
            $table->unsignedInteger('reserved_reviews')->default(0);
            $table->unsignedInteger('completed_reviews')->default(0);
            $table->string('status', 24)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'location']);
            $table->index(['app_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_review_campaigns');
    }
};
