<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flutterwave_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('reference')->unique();           // OUR reference — sent to Flutterwave as `reference`
            $table->string('provider')->default('flutterwave');
            $table->string('gateway_reference')->nullable(); // Flutterwave's transfer id (data.id)
            $table->string('destination_type')->default('bank_account');
            $table->string('currency', 8);
            $table->string('debit_currency', 8)->nullable(); // wallet debited (cross-currency transfers)
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 18, 2)->nullable();
            $table->string('status')->default('processing');  // processing | pending | success | failed | unknown
            $table->string('narration')->nullable();
            $table->string('note')->nullable();               // free-text audit note from the admin
            $table->string('bank_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();       // the name Flutterwave resolved for the account
            $table->string('customer_name')->nullable();      // who asked for the payout (kept for audit parity)
            $table->string('customer_email')->nullable();
            $table->string('beneficiary_name')->nullable();
            $table->string('message')->nullable();            // complete_message straight from Flutterwave
            // The provider's own handle for this payout. Flutterwave answers with a numeric transfer
            // id (stored in gateway_reference), so this stays null unless a provider supplies a trace.
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
        Schema::dropIfExists('flutterwave_payouts');
    }
};
