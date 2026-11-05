<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_rank_levels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('level')->unique();
            $table->string('name', 60);
            $table->string('icon', 500)->nullable();
            $table->unsignedInteger('minimum_subscribed_referrals');
            $table->decimal('minimum_referral_cash_inflow_usd', 14, 2);
            $table->decimal('minimum_user_cash_inflow_usd', 14, 2);
            $table->decimal('monthly_reward_usd', 10, 2);
            $table->unsignedTinyInteger('reward_duration_months');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_rank_qualifications', function (Blueprint $table): void {
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

        DB::table('system_settings')->insertOrIgnore([
            'setting_key' => 'rewards.user_ranking_active',
            'value' => json_encode(true),
            'is_public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('setting_key', 'rewards.user_ranking_active')->delete();
        Schema::dropIfExists('user_rank_qualifications');
        Schema::dropIfExists('user_rank_levels');
    }
};
