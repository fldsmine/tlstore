<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * moderation_decisions
 *
 * Moderation verdict for a review submission or an ad-share event. 
 *
 * Relationships:
 *   - review_submission_id -> review_submissions.id (null on delete)
 *   - ad_share_event_id    -> ad_share_events.id (cascade delete)
 *   - decided_by           -> users.id (null on delete)
 *   - decided_by_admin_id  -> admins.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ad_share_event_id')->nullable()->constrained('ad_share_events')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('decision_key', 120)->nullable()->unique();
            $table->string('decision', 32);
            $table->string('reason_code', 64)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedInteger('policy_version')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['review_submission_id', 'decided_at']);
            $table->index(['ad_share_event_id', 'decided_at'], 'moderation_ad_event_decided_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_decisions');
    }
};
