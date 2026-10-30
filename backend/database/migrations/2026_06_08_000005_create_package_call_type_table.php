<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_call_type', function (Blueprint $table) {
            $table->foreignUuid('package_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('call_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->decimal('rate_per_minute', 36, 6)->nullable();      // null = fall back to call_type
            $table->decimal('claimable_percentage', 5, 2)->nullable();  // null = fall back to call_type
            $table->timestamps();

            $table->unique(['package_id', 'call_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_call_type');
    }
};
