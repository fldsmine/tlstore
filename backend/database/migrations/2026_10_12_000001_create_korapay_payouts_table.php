<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('korapay_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('reference')->unique();          // OUR payout reference — also what Korapay is queried with
            $table->string('provider')->default('korapay');
            $table->string('gateway_reference')->nullable(); // Korapay's own reference once it responds
            $table->string('destination_type')->default('bank_account');
            $table->string('currency', 8);
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 18, 2)->nullable();
            $table->string('status')->default('processing');  // processing | pending | success | failed | unknown
            $table->string('narration')->nullable();
            $table->string('note')->nullable();               // free-text audit note from the admin
            $table->string('bank_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('trace_id')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('error')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('korapay_payouts');
    }
};
