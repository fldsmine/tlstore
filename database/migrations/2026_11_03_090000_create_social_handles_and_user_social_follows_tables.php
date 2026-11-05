<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_handles', function (Blueprint $table): void {
            $table->id();
            $table->string('handle_key', 64)->unique();
            $table->string('name', 100);
            $table->string('channel_url', 2048);
            $table->string('icon', 64)->nullable();
            $table->unsignedBigInteger('reward_amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('user_social_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_handle_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('completed');
            $table->unsignedBigInteger('reward_amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->foreignId('ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('followed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'social_handle_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_follows');
        Schema::dropIfExists('social_handles');
    }
};
