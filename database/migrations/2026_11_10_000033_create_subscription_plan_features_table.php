<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subscription_plan_features
 *
 * Marketing/limit feature rows attached to a package.
 *
 * Relationships:
 *   - subscription_plan_id -> subscription_plans.id (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('feature_key', 64);
            $table->string('label', 180);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['subscription_plan_id', 'feature_key'], 'subscription_plan_feature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_features');
    }
};
