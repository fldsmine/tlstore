<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_webhook_events
 *
 * Raw, de-duplicated gateway webhook payloads for replay/forensics.
 *
 * Relationships:
 *   - none - linked to payments only by provider_event_id
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 100);
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->string('processing_status', 24)->default('received');
            $table->text('processing_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id'], 'payment_webhook_provider_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
