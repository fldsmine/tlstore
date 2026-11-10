<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * user_subscriptions
 *
 * A purchased package and its validity window.
 *
 * Relationships:
 *   - user_id              -> users.id (cascade delete)
 *   - subscription_plan_id -> subscription_plans.id (restrict delete)
 *   - payment_intent_id    -> payment_intents.id (null on delete)
 *   - referenced by withdrawal_requests (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_type', 32);
            $table->string('status', 24)->default('pending');
            $table->string('provider_subscription_reference', 191)->nullable()->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('plan_snapshot');
            $table->timestamps();
            $table->index(['user_id', 'product_type', 'status', 'ends_at'], 'user_subscription_access_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
