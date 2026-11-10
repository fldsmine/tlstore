<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_payout_profiles
 *
 * Saved payout destination for a user (one per user).
 *
 * Relationships:
 *   - user_id              -> users.id (unique, cascade delete)
 *   - withdrawal_option_id -> withdrawal_options.id (restrict delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_payout_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('withdrawal_option_id')->constrained('withdrawal_options')->restrictOnDelete();
            $table->json('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_payout_profiles');
    }
};
