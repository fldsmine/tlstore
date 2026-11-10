<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * exchange_rate_snapshots
 *
 * Historical FX rates used for display conversion and USD-normalised rank metrics.
 *
 * Relationships:
 *   - none - references currency codes as plain chars
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rate_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 24, 12);
            $table->string('source', 100);
            $table->boolean('is_fallback')->default(false);
            $table->timestamp('fetched_at');
            $table->timestamp('source_published_at')->nullable();
            $table->timestamps();
            $table->unique(['base_currency', 'quote_currency', 'fetched_at'], 'fx_pair_fetched_unique');
            $table->index(['base_currency', 'quote_currency', 'fetched_at'], 'fx_pair_fetched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_snapshots');
    }
};
