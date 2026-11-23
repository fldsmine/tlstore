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
        Schema::create('vtu_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->string('service');                  // airtime | data | tv | electricity | education
            $table->string('service_id');               // VTpass serviceID: mtn, mtn-data, dstv, ikeja-electric…
            $table->string('wallet');                   // which wallet paid (User::WALLET_COLUMNS key)
            $table->string('variation_code')->nullable(); // data/TV/electricity/education plan code
            $table->string('billers_code')->nullable();   // meter/smartcard/phone the value is delivered to
            $table->string('phone')->nullable();          // contact phone sent to VTpass

            // Money: charged in NGN (VTpass currency); the USD equivalent is what left the wallet.
            $table->decimal('amount_ngn', 18, 2);
            $table->decimal('amount_usd', 36, 6);
            $table->decimal('fee', 36, 6)->default(0);  // service fee (net = amount_usd; 0 for now)

            $table->string('request_id')->unique();     // our idempotency key sent to VTpass (also the ledger ref)
            $table->string('external_id')->nullable();  // VTpass transactionId
            $table->string('status')->default('pending'); // App\Enums\VtuStatus
            $table->text('token')->nullable();          // electricity prepaid token / education pin, when returned
            $table->boolean('refunded')->default(false); // guard so a failed/reversed order refunds exactly once
            $table->uuid('transaction_id')->nullable(); // linked debit ledger entry
            $table->json('meta')->nullable();           // full VTpass response snapshot + display fields

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'wallet', 'status']); // policy: count successful purchases per wallet
            $table->index(['status', 'created_at']);        // requery sweep of pending orders
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vtu_orders');
    }
};
