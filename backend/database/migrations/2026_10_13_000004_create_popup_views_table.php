<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per user per popup — what they have already been shown.
     *
     * A non-recursive popup is spent the first time it reaches a user, so this row is what stops
     * it coming back. A recursive popup is never spent; its row only accumulates the shown /
     * dismissed counts the backoffice displays.
     */
    public function up(): void
    {
        Schema::create('popup_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('popup_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->unsignedInteger('shown_count')->default(0);
            $table->unsignedInteger('dismissed_count')->default(0);
            $table->timestamps();

            $table->unique(['popup_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popup_views');
    }
};
