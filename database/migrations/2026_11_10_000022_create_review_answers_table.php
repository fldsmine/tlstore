<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * review_answers
 *
 * One answer per question per submission.
 *
 * Relationships:
 *   - review_submission_id -> review_submissions.id (cascade delete)
 *   - review_question_id   -> review_questions.id (restrict delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_question_id')->constrained('review_questions')->restrictOnDelete();
            $table->json('answer_value');
            $table->timestamps();
            $table->unique(['review_submission_id', 'review_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_answers');
    }
};
