<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily referral-bonus target: the user sets a target each day; once the referral bonus
        // they earn AFTER it is set (created_at) reaches it, they unlock reward = target × reward_percent.
        // Claimed manually into the user's Rewards wallet. One row per user per day.
        Schema::create('reward_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('target_amount', 36, 6);
            $table->decimal('reward_amount', 36, 6)->default(0); // = target × percent, set when achieved
            $table->decimal('reward_percent', 5, 2)->default(0); // snapshot of the % used at achievement
            $table->string('status')->default('active');         // active | achieved | claimed | missed
            $table->timestamp('achieved_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_targets');
    }
};
