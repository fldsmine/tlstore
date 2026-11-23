<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_payment_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('processor');                       // general | flutterwave | …
            $table->unsignedInteger('sequence_number');        // per-sheet counter for the filename
            $table->decimal('total_amount', 18, 2)->default(0); // local-currency payout total (banks pay local)
            $table->string('currency', 3)->nullable();         // the sheet's payout currency (single-currency per sheet)
            $table->json('withdrawal_ids');                    // withdrawals included on the sheet
            $table->timestamps();

            $table->index('sequence_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_payment_sheets');
    }
};
