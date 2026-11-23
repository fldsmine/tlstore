<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paystack_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('reference')->unique();           // OUR payout reference (also the Paystack wire reference, lowercased)
            $table->string('provider')->default('paystack');
            $table->string('gateway_reference')->nullable(); // Paystack's transfer code (TRF_…)
            $table->string('destination_type')->default('bank_account');
            $table->string('currency', 8);
            // Paystack transfers are always funded from the single "balance" wallet, so this stays
            // empty; the column exists because the shared Send money flow records it for every
            // manager, and because Paystack may support a debit currency later.
            $table->string('debit_currency', 8)->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 18, 2)->nullable();
            $table->string('status')->default('processing');  // processing | pending | success | failed | unknown
            $table->string('narration')->nullable();
            $table->string('note')->nullable();               // free-text audit note from the admin
            $table->string('bank_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();       // the name Paystack resolved for the account
            $table->string('customer_name')->nullable();      // who asked for the payout (kept for audit parity)
            $table->string('customer_email')->nullable();
            $table->string('recipient_code')->nullable();     // Paystack's transfer recipient (RCP_…) used for this payout
            $table->string('message')->nullable();            // Paystack's own sentence about the transfer
            $table->string('trace_id')->nullable();           // Paystack answers with a transfer code, stored in gateway_reference
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
        Schema::dropIfExists('paystack_payouts');
    }
};
