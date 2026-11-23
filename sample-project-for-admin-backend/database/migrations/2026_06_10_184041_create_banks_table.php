<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code');                 // bank sort/clearing code (e.g. 044)
            $table->string('tag')->nullable();
            $table->string('type')->nullable();      // commercial | digital | microfinance | payment_service | …
            $table->string('country', 2)->default('NG')->index(); // ISO country; foreign providers added in Phase 3
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
