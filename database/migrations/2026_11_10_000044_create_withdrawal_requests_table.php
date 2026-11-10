<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * withdrawal_requests
 *
 * Payout requests. 
 *
 * Relationships:
 *   - user_id              -> users.id (cascade delete)
 *   - wallet_id            -> wallets.id (restrict delete)
 *   - user_subscription_id -> user_subscriptions.id (null on delete)
 *   - withdrawal_option_id -> withdrawal_options.id (restrict delete)
 *   - processed_by         -> users.id (null on delete)
 *   - processed_by_admin_id -> admins.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_subscription_id')->nullable()->constrained('user_subscriptions')->nullOnDelete();
            $table->foreignId('withdrawal_option_id')->constrained('withdrawal_options')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->char('currency', 3);
            $table->json('payout_details');
            $table->json('package_withdrawal_terms_snapshot')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 100)->unique();
            $table->string('provider_reference', 191)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('failure_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_subscription_id', 'requested_at'], 'withdrawal_package_requested_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
