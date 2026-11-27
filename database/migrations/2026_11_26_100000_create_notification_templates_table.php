<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('act', 64)->unique();
            $table->string('name', 120);
            $table->string('subject', 191)->nullable();
            $table->longText('email_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->json('shortcodes')->nullable();
            $table->boolean('email_status')->default(false);
            $table->boolean('sms_status')->default(false);
            $table->boolean('firebase_status')->default(false);
            $table->text('firebase_body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
