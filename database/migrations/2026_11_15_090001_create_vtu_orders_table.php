<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vtu_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('debit_ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();

            $table->string('service', 24); // airtime | data | tv | electricity | education
            $table->string('service_id', 80); // VTpass biller/network code
            $table->string('provider', 24)->default('vtpass');
            $table->string('variation_code', 120)->nullable();
            $table->text('customer_id')->nullable(); // encrypted by the model
            $table->text('customer_name')->nullable(); // encrypted by the model
            $table->string('phone', 32)->nullable();

            $table->unsignedBigInteger('amount_ngn_minor');
            $table->unsignedBigInteger('charged_amount_minor');
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 24, 12)->default(1);
            $table->unsignedBigInteger('fee_minor')->default(0);

            $table->string('status', 24)->default('pending');
            $table->string('request_id', 80)->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->char('payload_hash', 64);
            $table->string('external_id', 191)->nullable()->index();
            $table->text('token')->nullable(); // encrypted by the model
            $table->boolean('refunded')->default(false);
            $table->json('meta')->nullable(); // normalized, non-secret provider outcome and receipt labels
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['service', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vtu_orders');
    }
};
