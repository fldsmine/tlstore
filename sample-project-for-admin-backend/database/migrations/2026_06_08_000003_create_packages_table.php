<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->decimal('price', 36, 6)->default(0); // end-user price
            $table->decimal('agent_price', 36, 6)->default(0); // what an agent pays from their agent wallet
            $table->decimal('direct_referral_bonus', 36, 6)->default(0);   // upline (direct) earns on activation
            $table->decimal('indirect_referral_bonus', 36, 6)->default(0); // 2nd-level upline (spillover) earns on activation
            $table->unsignedInteger('tier_level')->unique();
            $table->decimal('daily_call_minutes', 8, 2)->default(0); // daily claim cap
            $table->unsignedInteger('call_id_limit')->default(1); // Call IDs the package grants (permanent)
            $table->json('features')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
