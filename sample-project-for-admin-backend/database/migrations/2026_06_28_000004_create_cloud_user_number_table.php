<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user's personal list of sponsor numbers — added from those available in their plan,
     * capped by the plan's number_quota. Only numbers on this list are callable.
     */
    public function up(): void
    {
        Schema::create('cloud_user_number', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('cloud_number_id')->constrained('cloud_numbers')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'cloud_number_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_user_number');
    }
};
