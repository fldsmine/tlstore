<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest eSIM orders from the product pages (product.n-calls.com).
 *
 * Deliberately its own table rather than a Transactions row: a guest has no account and
 * `transactions.user_id` is NOT NULL — writing guests into it would mean inventing users (or
 * weakening that constraint) for people who never signed up. This table carries only what the
 * storefront needs: who the buyer is, what they bought, what they were charged, and the payment
 * trail (our reference + the gateway's own reference, so a replayed callback/webhook can be
 * recognised and settled exactly once).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('reference')->unique();        // OURS (PRD-…) — the gateway echoes it back
            $table->uuid('package_id')->nullable();       // the product sold (no FK: audit snapshot)
            $table->string('product_slug');               // diamond-esim | royal-esim
            $table->string('product_name');               // "Diamond eSIM"

            $table->string('full_name');
            $table->string('phone');
            $table->string('email');

            $table->decimal('amount_usd', 18, 6);         // the package's USD price when the order started
            $table->decimal('amount_ngn', 18, 2);         // what the gateway is asked for (P2: the charge)
            $table->string('currency', 8)->default('NGN');

            $table->string('provider')->nullable();       // paystack (config: product.provider)
            $table->string('gateway_reference')->nullable(); // the provider's own transaction id
            $table->text('checkout_url')->nullable();     // the payment link the buyer was sent to
            $table->string('status')->default('pending'); // pending | paid | failed
            $table->json('gateway_response')->nullable(); // what the provider said, for reconciliation
            $table->string('failure_reason')->nullable();

            $table->timestamp('paid_at')->nullable();
            // Both set inside the settling transaction, so a replayed settle can never send twice.
            $table->timestamp('confirmation_sent_at')->nullable(); // buyer receipt
            $table->timestamp('ops_notified_at')->nullable();      // staff fulfilment nudge
            $table->timestamp('fulfilled_at')->nullable();         // the product was sent by hand
            $table->string('fulfilment_note')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('meta')->nullable();             // region, channel, reconcile flags…

            $table->timestamps();

            $table->index(['status', 'created_at']);       // the fulfilment queue + the requery sweep
            $table->index('email');                       // "did this buyer pay?" support lookup
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_orders');
    }
};
