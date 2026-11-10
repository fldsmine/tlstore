<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * review_submissions
 *
 * Answers submitted for a review task, awaiting moderation.
 *
 * Relationships:
 *   - review_task_id             -> review_tasks.id (unique, cascade delete)
 *   - reviewer_id                -> users.id (cascade delete)
 *   - review_template_version_id -> review_template_versions.id (restrict delete)
 *   - has many review_answers, moderation_decisions, risk_signals
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_task_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('review_template_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('submitted');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['reviewer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_submissions');
    }
};
