<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * risk_signals
 *
 * Fraud / quality signals attached to a submission or share event.
 *
 * Relationships:
 *   - review_submission_id -> review_submissions.id (cascade delete)
 *   - ad_share_event_id    -> ad_share_events.id (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ad_share_event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('signal_key', 120)->nullable()->unique();
            $table->string('signal_type', 64);
            $table->string('severity', 24)->default('info');
            $table->string('source', 48);
            $table->string('rule_version', 48)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['review_submission_id', 'severity']);
            $table->index(['ad_share_event_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_signals');
    }
};
