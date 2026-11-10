<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * referral_codes
 *
 * Shareable referral codes owned by a user.
 *
 * Relationships:
 *   - user_id -> users.id (cascade delete)
 *   - has many referral_attributions
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->string('status', 24)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
