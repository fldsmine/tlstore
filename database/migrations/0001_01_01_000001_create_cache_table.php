<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cache
 *
 * Laravel cache store (database driver). Framework table, no application relations.
 *
 * Relationships:
 *   - self contained; written by the cache repository
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
    }
};
