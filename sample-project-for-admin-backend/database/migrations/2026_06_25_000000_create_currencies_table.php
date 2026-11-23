<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 3)->unique();                     // ISO 4217 (NGN, GHS, XOF…)
            $table->string('name');                                  // full currency name
            $table->string('symbol')->nullable();                    // display symbol (₦, ₵, $…)
            $table->decimal('usd_exchange_rate', 18, 4)->default(0); // local currency per 1 USD (managed here, shared by every country on this currency)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
