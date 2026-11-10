<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_rank_qualifications
 *
 * A user's achieved rank, the metrics frozen at qualification time and the reward schedule.
 *
 * Relationships:
 *   - user_id           -> users.id (cascade delete)
 *   - user_rank_level_id -> user_rank_levels.id (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_rank_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_rank_level_id')->constrained('user_rank_levels')->cascadeOnDelete();
            $table->unsignedInteger('subscribed_referrals_at_qualification');
            $table->decimal('referral_cash_inflow_usd_at_qualification', 14, 2);
            $table->decimal('user_cash_inflow_usd_at_qualification', 14, 2);
            $table->decimal('monthly_reward_usd_at_qualification', 10, 2);
            $table->unsignedTinyInteger('reward_duration_months_at_qualification');
            $table->timestamp('qualified_at');
            $table->timestamp('next_reward_at')->nullable();
            $table->unsignedTinyInteger('rewards_paid')->default(0);
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['user_id', 'user_rank_level_id'], 'rank_qualification_user_level_unique');
            $table->index(['status', 'next_reward_at'], 'rank_qualification_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rank_qualifications');
    }
};
