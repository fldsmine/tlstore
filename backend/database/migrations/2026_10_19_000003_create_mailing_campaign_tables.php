<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('name', 160);
            $table->string('subject', 180);
            $table->longText('html_body');
            $table->longText('text_body')->nullable();
            $table->string('reply_to', 254)->nullable();
            $table->timestamps();

            $table->index(['name', 'created_at']);
        });

        Schema::create('mailing_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('mailing_templates')->nullOnDelete();
            $table->string('name', 160);
            $table->string('subject', 180);
            $table->longText('html_body');
            $table->longText('text_body')->nullable();
            $table->string('reply_to', 254)->nullable();
            $table->json('filters');
            $table->string('status', 32)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('sending_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_code', 120)->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['admin_id', 'created_at']);
        });

        Schema::create('mailing_campaign_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('mailing_campaigns')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error_code', 120)->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'user_id']);
            $table->index(['campaign_id', 'status', 'id']);
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_campaign_recipients');
        Schema::dropIfExists('mailing_campaigns');
        Schema::dropIfExists('mailing_templates');
    }
};
