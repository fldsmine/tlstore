<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('currency', 10);
            $table->string('symbol', 5);
            $table->integer('method_code');
            $table->string('gateway_alias')->unique();
            $table->decimal('percent_charge', 5, 2)->default(0);
            $table->decimal('fixed_charge', 10, 2)->default(0);
            $table->decimal('rate', 10, 2);
            $table->boolean('status')->default(true);
            $table->text('description')->nullable();
            $table->json('addresses'); // array of address strings
            $table->string('logo')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_options');
    }
};
