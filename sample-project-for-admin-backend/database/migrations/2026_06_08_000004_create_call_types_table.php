<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('key')->unique(); // CallType enum value
            $table->string('description')->nullable();
            $table->string('color')->nullable(); // hex badge colour
            $table->decimal('rate_per_minute', 36, 6)->default(0);
            $table->decimal('claimable_percentage', 5, 2)->default(0);
            // Generation config for the daily shared call pool.
            $table->decimal('min_minutes', 10, 2)->default(1);
            $table->decimal('max_minutes', 10, 2)->default(60);
            $table->unsignedInteger('min_per_batch')->default(1);
            $table->unsignedInteger('max_per_batch')->default(10);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_types');
    }
};
