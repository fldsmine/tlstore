<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_minute_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->decimal('price', 36, 6)->default(0);       // end-user price
            $table->decimal('agent_price', 36, 6)->default(0); // agent wallet price
            $table->decimal('daily_call_minutes', 8, 2)->default(0);
            $table->unsignedInteger('duration_days')->default(30); // expires (not lifetime)
            $table->unsignedInteger('paylater_duration_days')->default(14); // Pay Later repayment window
            $table->decimal('min_earning_rate', 36, 6)->default(50);        // headline earning rate per plan
            $table->unsignedInteger('call_id_bonus')->default(0);  // extra Call IDs granted (expire with duration_days)
            $table->decimal('referral_bonus', 36, 6)->default(0);  // direct-only bonus to the buyer's upline (no spillover)
            $table->json('features')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_minute_plans');
    }
};
