<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * notification_deliveries
 *
 * Per-device / per-channel delivery attempts for a notification.
 *
 * Relationships:
 *   - notification_id -> notifications.id (cascade delete)
 *   - user_device_id   -> user_devices.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 24)->default('push');
            $table->string('provider', 32)->nullable();
            $table->string('status', 24)->default('queued');
            $table->string('provider_message_id', 191)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['notification_id', 'status']);
            $table->unique(['notification_id', 'user_device_id', 'channel'], 'notification_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
