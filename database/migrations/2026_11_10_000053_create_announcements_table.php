<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * announcements
 *
 * Platform announcements (dialog / inline banners) with a display window.
 *
 * Relationships:
 *   - has many user_announcement_dismissals
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('body');
            $table->string('action_url', 255)->nullable();
            $table->string('action_label', 60)->nullable();
            $table->boolean('is_dismissible')->default(true);
            $table->string('type', 255)->default('info');
            $table->unsignedTinyInteger('display_type')->default(0)
                ->comment('0=pop dialog, 1=inline below earnings, 2=pinned inline');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
