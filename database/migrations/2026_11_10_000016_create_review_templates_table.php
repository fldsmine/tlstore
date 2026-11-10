<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * review_templates
 *
 * Named questionnaire templates; each has one or more versions.
 *
 * Relationships:
 *   - has many review_template_versions
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 64)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_templates');
    }
};
