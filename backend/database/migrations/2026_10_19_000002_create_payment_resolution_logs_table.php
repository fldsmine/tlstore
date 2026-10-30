<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_resolution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            // Keep audit rows even if a user/order is later removed; the original reference remains searchable.
            $table->uuid('transaction_id')->nullable();
            $table->uuid('product_order_id')->nullable();
            $table->string('reference');
            $table->string('provider')->nullable();
            $table->string('action'); // provider_recheck | manual_confirm | closed_unpaid
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('verification_source')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->text('note')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['reference', 'created_at']);
            $table->index(['transaction_id', 'created_at']);
            $table->index(['product_order_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_resolution_logs');
    }
};
