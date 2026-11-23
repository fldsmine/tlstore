<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocated_numbers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 5);   // e.g. 0803 — encodes the (fake) network
            $table->string('suffix', 10);  // randomly generated unique-ish part
            $table->string('source')->default('package'); // package | additional
            $table->uuid('additional_minute_plan_id')->nullable()->index();
            $table->uuid('minute_top_up_id')->nullable()->index(); // the MinuteTopUp that granted this (additional only)
            $table->timestamp('expires_at')->nullable(); // null = permanent (package); set = expires (additional)
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocated_numbers');
    }
};
