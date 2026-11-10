<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * social_handles
 *
 * Official channels a user can follow for a reward.
 *
 * Relationships:
 *   - has many user_social_follows
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_handles', function (Blueprint $table) {
            $table->id();
            $table->string('handle_key', 64)->unique();
            $table->string('name', 100);
            $table->string('channel_url', 2048);
            $table->string('icon', 64)->nullable();
            $table->unsignedBigInteger('reward_amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_handles');
    }
};
