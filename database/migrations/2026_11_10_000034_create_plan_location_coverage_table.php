<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * plan_location_coverage
 *
 * Countries/regions a package grants access to.
 *
 * Relationships:
 *   - subscription_plan_id -> subscription_plans.id (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_location_coverage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->string('target_code', 32);
            $table->timestamps();
            $table->unique(['subscription_plan_id', 'target_type', 'target_code'], 'plan_location_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_location_coverage');
    }
};
