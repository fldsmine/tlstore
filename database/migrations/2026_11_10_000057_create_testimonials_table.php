<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * testimonials
 *
 * User testimonials shown on the marketing pages, gated by moderation.
 *
 * Relationships:
 *   - user_id     -> users.id (cascade delete)
 *   - approved_by -> users.id (no delete action; the row is kept if the admin is removed)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('rating')->default(5)->comment('1-5 stars');
            $table->string('role')->nullable()->comment('e.g. AI Creator, Freelancer');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
