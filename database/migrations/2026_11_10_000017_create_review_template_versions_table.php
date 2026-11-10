<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * review_template_versions
 *
 * Immutable published versions of a review template.
 *
 * Relationships:
 *   - review_template_id -> review_templates.id (cascade delete)
 *   - has many review_template_version_questions
 *   - referenced by review_tasks and review_submissions (restrict delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['review_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_template_versions');
    }
};
