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

        Schema::create('ad_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ad_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ad_version');
            $table->decimal('amount', 36, 6);    // reward credited (snapshot, USD 4dp)
            $table->timestamp('shared_at');
            $table->timestamps();

            $table->unique(['user_id', 'ad_id', 'ad_version']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_shares');
    }
};
