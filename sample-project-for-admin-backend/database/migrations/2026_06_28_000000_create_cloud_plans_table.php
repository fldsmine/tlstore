<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_storage_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('token_price', 36, 6)->default(0);   // USD resale price of the Cloud activation token
            $table->decimal('agent_price', 36, 6)->default(0);   // USD cost a vendor pays to generate it
            $table->unsignedInteger('number_quota')->default(0); // max sponsor numbers a user may add (0 = unlimited)
            $table->decimal('max_earning', 18, 4)->default(0);   // daily earning cap (0 = no cap)
            $table->decimal('max_earning_per_hour', 18, 4)->default(0); // hourly earning cap (0 = no cap) — paces earning so users return
            $table->unsignedInteger('duration_days')->default(0); // plan validity after activation (0 = never expires)
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_storage_plans');
    }
};
