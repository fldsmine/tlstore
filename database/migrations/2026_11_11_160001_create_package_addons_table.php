<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_plan_id')->unique()->constrained()->restrictOnDelete();
            $table->string('addon_key', 64)->unique();
            $table->string('name', 120);
            $table->unsignedBigInteger('price_minor');
            $table->decimal('discount', 5, 2)->default(0)->comment('Percentage discount applied to the add-on price');
            $table->char('currency', 3)->default('USD');
            $table->string('billing_interval', 24)->default('monthly');
            $table->unsignedSmallInteger('duration_count')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'subscription_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_addons');
    }
};
