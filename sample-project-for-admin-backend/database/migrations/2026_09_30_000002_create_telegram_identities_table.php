<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authenticated Telegram identity linked to a Nextel account.
 *
 * One row per user (a user links exactly one Telegram account) and one
 * Telegram account per user (telegram_user_id is globally unique) — this
 * prevents one Telegram account from being attached to several accounts
 * and vice versa. Only the fields needed for authentication/display are
 * stored; nothing sensitive from Telegram is persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('language_code', 16)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_identities');
    }
};
