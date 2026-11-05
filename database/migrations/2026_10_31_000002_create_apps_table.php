<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('prototype_id')->unique();
            $table->string('name', 180);
            $table->string('short_code', 12);
            $table->string('store', 80);
            $table->string('brand_color', 7);
            $table->string('developer', 180);
            $table->string('category', 100);
            $table->string('install_count_label', 32)->nullable();
            $table->text('description');
            $table->string('illustration_kind', 40)->nullable();
            $table->integer('featured')->default(0)->comment('If listing is featured or not [0,1], featured shows on HomeScreen and special pages');
            $table->timestamps();

            $table->index(['store', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
