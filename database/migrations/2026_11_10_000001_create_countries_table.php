<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * countries
 *
 * Reference list of countries. Drives registration, campaign targeting and payouts.
 *
 * Relationships:
 *   - has many users (users.country_code)
 *   - has many region_countries (region_countries.country_code)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->string('name', 120);
            $table->char('default_currency', 3)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
