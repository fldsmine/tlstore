<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * review_questions
 *
 * Question bank used to build review questionnaires.
 *
 * Relationships:
 *   - has many review_template_version_questions
 *   - has many review_answers
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question_key', 64)->unique();
            $table->string('step_name', 120);
            $table->unsignedSmallInteger('step_order');
            $table->unsignedSmallInteger('question_order');
            $table->text('prompt')->nullable();
            $table->text('hint')->nullable();
            $table->string('type', 32);
            $table->json('options')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_attention_check')->default(false);
            $table->json('attention_check_answer')->nullable();
            $table->timestamps();
            $table->index(['step_order', 'question_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_questions');
    }
};
