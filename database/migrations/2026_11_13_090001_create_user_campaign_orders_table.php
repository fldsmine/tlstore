<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_campaign_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('campaign_name', 120);
            $table->string('platform', 24);
            $table->string('action', 32);
            $table->unsignedInteger('quantity');
            $table->string('target_url', 2048);
            $table->text('instructions')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->decimal('service_fee_percent', 5, 2)->default(5.00);
            $table->unsignedBigInteger('service_fee_minor');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('debited_amount_minor');
            $table->char('debited_currency', 3);
            $table->string('status', 24)->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('idempotency_key', 80)->unique();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_campaign_orders');
    }
};
