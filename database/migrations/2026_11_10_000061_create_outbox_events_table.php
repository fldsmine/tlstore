<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * outbox_events
 *
 * Transactional outbox of domain events awaiting dispatch to the queue.
 *
 * Relationships:
 *   - none - polymorphic aggregate_type / aggregate_id reference
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 80);
            $table->string('aggregate_id', 100);
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
