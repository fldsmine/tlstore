<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which sponsor numbers are available in which plans — a number is only callable by users
     * whose plan it is attached to (premium numbers can be exclusive to higher tiers).
     */
    public function up(): void
    {
        Schema::create('cloud_number_plan', function (Blueprint $table) {
            $table->foreignUuid('cloud_number_id')->constrained('cloud_numbers')->cascadeOnDelete();
            $table->foreignUuid('cloud_storage_plan_id')->constrained('cloud_storage_plans')->cascadeOnDelete();
            $table->primary(['cloud_number_id', 'cloud_storage_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_number_plan');
    }
};
