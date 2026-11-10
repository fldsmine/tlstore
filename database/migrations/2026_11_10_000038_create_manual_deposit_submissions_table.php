<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * manual_deposit_submissions
 *
 * Bank-transfer deposits uploaded by a user and reviewed by staff. 
 *
 * Relationships:
 *   - payment_intent_id   -> payment_intents.id (unique, null on delete)
 *   - user_id             -> users.id (cascade delete)
 *   - reviewed_by         -> users.id (null on delete)
 *   - reviewed_by_admin_id -> admins.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_deposit_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('bank_reference', 191)->nullable();
            $table->string('proof_storage_path', 512)->nullable();
            $table->string('status', 24)->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->unique('payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_deposit_submissions');
    }
};
