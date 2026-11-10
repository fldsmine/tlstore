<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * review_template_version_questions
 *
 * Pivot: which questions a template version asks, and in what order.
 *
 * Relationships:
 *   - review_template_version_id -> review_template_versions.id (cascade delete)
 *   - review_question_id         -> review_questions.id (restrict delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_template_version_questions', function (Blueprint $table) {
            $table->id();
			$table->foreignId('review_template_version_id')
				  ->constrained('review_template_versions')
				  ->onDelete('cascade')
				  ->index('rtv_questions_rtv_id_foreign'); // Shorter identifier name
            $table->foreignId('review_question_id')->constrained('review_questions')->restrictOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('step_name', 120);
            $table->unsignedSmallInteger('question_order');
            $table->timestamps();
            $table->unique(['review_template_version_id', 'review_question_id'], 'review_template_review_question_ids_unique');
            $table->index(['review_template_version_id', 'step_order', 'question_order'], 'rtvq_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_template_version_questions');
    }
};
