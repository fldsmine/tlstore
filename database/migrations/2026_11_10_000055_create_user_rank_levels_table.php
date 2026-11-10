<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_rank_levels
 *
 * Configured rank tiers and the monthly reward each one pays.
 *
 * Relationships:
 *   - has many user_rank_qualifications
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_rank_levels', function (Blueprint $table) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rank_levels');
    }
};
