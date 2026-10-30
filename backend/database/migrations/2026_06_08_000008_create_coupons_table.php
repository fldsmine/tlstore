<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('type'); // CouponType: activation|upgrade|extra_minutes
            $table->string('region')->default('nigeria')->index(); // nigeria | foreign — the issuing vendor's class
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete(); // redeemer
            $table->foreignUuid('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('package_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('additional_minute_plan_id')->nullable()->index(); // FK added in Phase C
            $table->uuid('cloud_storage_plan_id')->nullable()->index(); // Cloud-plan token target (cloud_storage_plans created later — relation only)
            $table->foreignUuid('batch_id')->nullable()->constrained('coupon_batches')->nullOnDelete();
            $table->decimal('price', 36, 6)->nullable();
            $table->boolean('is_special')->default(false);
            $table->timestamp('used_at')->nullable()->index(); // null = valid/unredeemed
            $table->timestamps();

            // FK columns are NOT auto-indexed on Postgres (prod) — index the hot agent/batch filters.
            $table->index(['agent_id', 'used_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
