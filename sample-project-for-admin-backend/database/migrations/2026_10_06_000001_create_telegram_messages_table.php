<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound bot DMs from Mini App users and outbound replies/broadcasts from
 * the backoffice. Telegram will only deliver to people who have started the
 * bot; failed sends are stored so admins can see who never got the message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('telegram_identity_id')->constrained('telegram_identities')->cascadeOnDelete();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('direction', 16);
            $table->text('body');
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->string('status', 16);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['telegram_identity_id', 'created_at']);
            $table->index(['direction', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
