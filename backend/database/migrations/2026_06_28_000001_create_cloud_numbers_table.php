<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_numbers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');                              // saved name, e.g. "BetKing Bonus Drop"
            $table->string('line_number');                       // displayed line/phone number
            $table->string('brand')->nullable();                 // brand label, e.g. "BetKing"
            $table->string('caller_name')->nullable();           // person who "calls" on behalf of the brand, e.g. "Adaeze"
            $table->string('avatar')->nullable();                // logo path on the public disk
            $table->text('popup_message')->nullable();           // the promo shown as a pop-up during the call
            $table->string('claim_url')->nullable();             // optional "Claim" CTA link on the pop-up
            $table->decimal('earning_per_call', 36, 6)->default(0); // USD earned per completed call to this number
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_numbers');
    }
};
