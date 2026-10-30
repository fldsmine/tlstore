<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // AdSense-style ads, admin-managed. Each carries its own admin-set earning amount;
        // sharing it credits the user once per `version` (a reward change bumps the version,
        // re-opening it). Impression/view figures grow daily so engagement looks alive.
        Schema::create('ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('advertiser');
            $table->string('image_url')->nullable();
            $table->string('target_url')->nullable();
            $table->decimal('reward', 36, 6)->default(0);       // admin-set earning per share (USD, 4dp — earnings can be sub-cent)
            $table->unsignedInteger('version')->default(1);     // bumps when reward changes → re-earnable
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('shares')->default(0);       // real shares from users
            $table->unsignedInteger('daily_impression_growth')->default(0);
            $table->unsignedInteger('daily_view_growth')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
