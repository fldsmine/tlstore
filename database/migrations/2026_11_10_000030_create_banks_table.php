<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * banks
 *
 * Bank directory used for payout account validation. 
 *
 * Relationships:
 *   - looked up by user_payout_profiles.details (no direct FK)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code');
            $table->string('paystack_code')->nullable();
            $table->string('flutterwave_code')->nullable();
            $table->string('institution_code')->nullable()->comment('NIBSS/NIP institution code used on payout sheets');
            $table->string('tag')->nullable();
            $table->string('type')->nullable()->comment('commercial | digital | microfinance | payment_service');
            $table->string('country', 2)->default('NG')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['country', 'code'], 'banks_country_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
