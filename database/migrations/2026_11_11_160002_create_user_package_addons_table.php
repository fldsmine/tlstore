<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_package_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_addon_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->json('features')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'package_addon_id', 'status', 'ends_at'], 'user_package_addon_access_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_package_addons');
    }
};
