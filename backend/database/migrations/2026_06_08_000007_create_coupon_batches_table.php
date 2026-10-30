<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('package_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('additional_minute_plan_id')->nullable()->index(); // FK added in Phase C
            $table->uuid('cloud_storage_plan_id')->nullable()->index(); // Cloud-plan token target
            $table->string('type'); // CouponType
            $table->string('region')->default('nigeria')->index(); // nigeria | foreign — the issuing vendor's class
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_batches');
    }
};
