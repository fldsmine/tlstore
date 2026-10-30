<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('wallet');                          // which wallet it was paid from
            $table->decimal('amount', 36, 6);                  // gross requested
            $table->decimal('fee', 36, 6)->default(0);         // processing fee
            $table->decimal('amount_paid', 36, 6);             // net = amount - fee (USD)
            // Local-currency payout snapshot (USD is the base; banks pay in the user's local currency).
            $table->string('local_currency', 3)->nullable();          // payout currency code (NGN, GHS, …)
            $table->decimal('local_amount', 18, 2)->nullable();        // gross in local currency (amount × snapshot rate)
            $table->decimal('local_amount_paid', 18, 2)->nullable();   // net in local currency — what the bank actually pays
            $table->string('status')->default('pending');      // App\Enums\WithdrawalStatus
            // Bank payout details (bank_name/bank_code/bank_tag/account_number/account_name) are
            // snapshotted into `meta` at request time — see Withdrawal model accessors.
            $table->foreignUuid('transaction_id')->nullable(); // linked ledger debit
            $table->json('meta')->nullable();
            $table->timestamp('processed_at')->nullable();     // when an admin completed/rejected it
            $table->timestamp('exported_at')->nullable();      // when put on a payment sheet (keeps the 4-status enum)
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
