<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subscription_groups
 *
 * Top-level package groups (ADS SHARE, ANDROID REVIEW, IOS REVIEW) that subscription plans hang from.
 *
 * Relationships:
 *   - has many subscription_plans
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 32)->unique();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_groups');
    }
};
