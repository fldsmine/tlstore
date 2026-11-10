<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * referral_attributions
 *
 * Links a referred user to the code that brought them in.
 *
 * Relationships:
 *   - referral_code_id  -> referral_codes.id (restrict delete)
 *   - referrer_user_id  -> users.id (cascade delete)
 *   - referred_user_id  -> users.id (unique, cascade delete)
 *   - has many referral_rewards
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('attributed');
            $table->timestamp('attributed_at');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_attributions');
    }
};
