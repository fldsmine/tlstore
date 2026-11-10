<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * region_countries
 *
 * Pivot: which countries belong to which region.
 *
 * Relationships:
 *   - region_id      -> regions.id   (cascade delete)
 *   - country_code   -> countries.code (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->timestamps();
            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->unique(['region_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_countries');
    }
};
