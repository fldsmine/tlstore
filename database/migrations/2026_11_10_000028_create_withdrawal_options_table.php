<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * withdrawal_options
 *
 * Payout channels (bank transfer, crypto, ...) with their dynamic form fields and fees.
 *
 * Relationships:
 *   - has many withdrawal_requests
 *   - has many user_payout_profiles
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_options', function (Blueprint $table) {
            $table->id();
            $table->string('option_id', 191)->unique();
            $table->string('name', 191);
            $table->string('icon', 191)->nullable();
            $table->text('description')->nullable();
            $table->longText('fields');
            $table->char('currency', 3)->default('USD');
            $table->decimal('tax', 5, 2)->default(0.00);
            $table->decimal('min_withdrawal', 5, 2)->default(5.00);
            $table->boolean('is_available')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_options');
    }
};
