<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_social_accounts
 *
 * Social handles a user has submitted for ad-share verification (one row per platform).
 *
 * Relationships:
 *   - user_id -> users.id (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 48);
            $table->string('handle', 255);
            $table->timestamps();
            $table->unique(['user_id', 'platform']);
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_accounts');
    }
};
