<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cache_locks
 *
 * Laravel atomic cache locks (database driver). Framework table, no application relations.
 *
 * Relationships:
 *   - self contained; written by the cache lock repository
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
    }
};
