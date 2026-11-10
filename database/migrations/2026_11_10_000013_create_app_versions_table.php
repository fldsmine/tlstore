<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * app_versions
 *
 * Mobile app release records used by the in-app update check.
 *
 * Relationships:
 *   - created_by -> admins.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 16);
            $table->string('version_name', 32);
            $table->unsignedInteger('build_number');
            $table->unsignedInteger('minimum_supported_build')->default(0);
            $table->string('title', 180)->nullable();
            $table->text('release_notes')->nullable();
            $table->string('direct_download_path')->nullable();
            $table->string('direct_download_url', 2048)->nullable();
            $table->string('play_store_url', 2048)->nullable();
            $table->string('app_store_url', 2048)->nullable();
            $table->boolean('force_update')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['platform', 'build_number']);
            $table->index(['platform', 'is_active', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
