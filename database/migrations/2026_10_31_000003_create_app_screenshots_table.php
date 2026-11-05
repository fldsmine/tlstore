<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('source_type', 32)->default('store_asset');
            $table->string('storage_disk', 64)->nullable();
            $table->string('storage_path')->nullable();
            $table->unsignedSmallInteger('illustration_index')->nullable();
            $table->string('alt_text', 255);
            $table->timestamps();

            $table->unique(['app_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_screenshots');
    }
};
