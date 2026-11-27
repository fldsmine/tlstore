<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery log for everything the platform posts to Telegram. The row is the
 * unit of work for queued deliveries, so a retried job can never send twice
 * (`status = sent`) and the admin console has a history with dates + results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 40);
            $table->string('chat_id', 64);
            $table->text('body')->nullable();
            $table->string('attachment_type', 16)->nullable();
            $table->string('attachment_path', 191)->nullable();
            $table->string('status', 16)->default('queued');
            $table->string('dedupe_key', 120)->nullable()->unique();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('sent_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('withdrawal_request_id')->nullable()->constrained('withdrawal_requests')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
