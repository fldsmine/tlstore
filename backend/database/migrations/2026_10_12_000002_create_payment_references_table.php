<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-use ledger for settled gateway payments. A row is written the moment a charge is
        // reconciled and about to be credited, and NOTHING may credit a reference that already has a
        // row. The composite unique index extends that guarantee to the provider's own transaction
        // id, so one real payment can never settle two charges.
        Schema::create('payment_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();                    // OUR reference (Transactions.reference)
            $table->uuid('transaction_id')->nullable();               // the charge it settled (no FK: audit record)
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->nullable();                   // korapay | paystack | flutterwave
            $table->string('gateway_reference')->nullable();          // the provider's transaction id
            $table->string('type');                                   // App\Enums\TransactionType
            $table->string('currency', 8)->nullable();
            $table->decimal('amount_ngn', 18, 2)->default(0);         // the reconciled amount that was credited
            $table->timestamp('settled_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'gateway_reference']);
            $table->index('settled_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_references');
    }
};
