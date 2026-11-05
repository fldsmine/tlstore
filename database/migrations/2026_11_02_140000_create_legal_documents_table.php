<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title', 191);
            $table->string('eyebrow', 120)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->text('hero_text')->nullable();
            $table->json('sections');
            $table->string('version', 32)->default('1.0');
            $table->date('last_updated')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['is_published', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
