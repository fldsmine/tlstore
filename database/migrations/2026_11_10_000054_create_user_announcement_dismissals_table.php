<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_announcement_dismissals
 *
 * Which announcements a user has dismissed.
 *
 * Relationships:
 *   - user_id         -> users.id (cascade delete)
 *   - announcement_id -> announcements.id (cascade delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('dismissed_at');
            $table->timestamps();
            $table->unique(['user_id', 'announcement_id']);
            $table->index(['user_id', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_announcement_dismissals');
    }
};
