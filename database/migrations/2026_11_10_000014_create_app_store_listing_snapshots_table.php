<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * app_store_listing_snapshots
 *
 * Point-in-time copies of an app-store listing (rating, review count, raw metadata).
 *
 * Relationships:
 *   - app_id -> apps.id (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_store_listing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('store', 80);
            $table->string('external_listing_id', 191)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedBigInteger('total_reviews')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->index(['app_id', 'captured_at']);
            $table->index(['store', 'external_listing_id']);
            $table->unique(['app_id', 'store', 'captured_at'], 'app_store_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_store_listing_snapshots');
    }
};
