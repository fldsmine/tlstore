<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_handles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('platform', 32);
            $table->string('name', 120);
            $table->string('handle', 150)->nullable();
            $table->string('url', 500);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Active social pages are mandatory before a user can enter the account dashboard.
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('user_social_follows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            // Handles are disabled rather than deleted so their follow history remains auditable.
            $table->foreignUuid('social_handle_id')->constrained('social_handles')->restrictOnDelete();
            $table->foreignUuid('unfollowed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('followed_at');
            $table->timestamp('unfollowed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'social_handle_id']);
            $table->index(['social_handle_id', 'unfollowed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_follows');
        Schema::dropIfExists('social_handles');
    }
};
