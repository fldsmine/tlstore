<?php

namespace App\Application\Actions\Reviews;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Models\ReviewAnswer;
use App\Models\ReviewSubmission;
use App\Models\ReviewTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitReview
{
    public function __construct(private readonly ListingEligibility $eligibility) {}

    public function handle(User $user, ReviewTask $task, array $answers, ?int $durationSeconds): ReviewSubmission
    {
        return DB::transaction(function () use ($user, $task, $answers, $durationSeconds): ReviewSubmission {
            $task = ReviewTask::query()->with('templateVersion.questions', 'campaign')->lockForUpdate()->findOrFail($task->id);
            if ($task->reviewer_id !== $user->id || $task->status->value !== 'reserved' || ($task->expires_at && $task->expires_at->isPast())) {
                throw ValidationException::withMessages(['task' => 'This review task cannot be submitted.']);
            }
            if (! $this->eligibility->canAccess($user, $task->campaign->location)) {
                throw ValidationException::withMessages(['task' => 'Your subscription or location eligibility is no longer active.']);
            }

            $questions = $task->templateVersion->questions->keyBy('id');
            if (count($answers) !== $questions->count() || count(array_unique(array_column($answers, 'question_id'))) !== $questions->count()) {
                throw ValidationException::withMessages(['answers' => 'Provide one answer for every question in the assigned questionnaire.']);
            }
            foreach ($answers as $answer) {
                $question = $questions->get($answer['question_id'] ?? null);
                if (! $question) {
                    throw ValidationException::withMessages(['answers' => 'An answer references a question outside this task questionnaire.']);
                }
                $this->validateAnswer($question, $answer['value'] ?? null);
            }

            $submission = ReviewSubmission::query()->create([
                'review_task_id' => $task->id,
                'reviewer_id' => $user->id,
                'review_template_version_id' => $task->review_template_version_id,
                'status' => 'pending_review',
                'duration_seconds' => $durationSeconds,
                'submitted_at' => now(),
            ]);
            foreach ($answers as $answer) {
                ReviewAnswer::query()->create([
                    'review_submission_id' => $submission->id,
                    'review_question_id' => $answer['question_id'],
                    'answer_value' => ['value' => $answer['value']],
                ]);
            }
            $task->update(['status' => 'submitted', 'completed_at' => now()]);

            return $submission->load('answers.question', 'task.campaign.app');
        });
    }

    private function validateAnswer(object $question, mixed $value): void
    {
        $type = $question->type->value;
        $options = $question->options ?? [];
        $valid = match ($type) {
            'single_choice' => is_string($value) && in_array($value, $options, true),
            'multiple_choice' => is_array($value) && $value !== [] && count(array_diff($value, $options)) === 0,
            'rating' => is_numeric($value)
                && (float) $value >= (float) ($question->settings['scale']['min'] ?? 1)
                && (float) $value <= (float) ($question->settings['scale']['max'] ?? 5),
            'long_text' => is_string($value) && str_word_count($value) >= (int) ($question->settings['min_words'] ?? 1),
            'confirmation' => is_bool($value),
            default => false,
        };
        if (! $valid) {
            throw ValidationException::withMessages(['answers' => "An answer is invalid for question {$question->question_key}."]);
        }
    }
}
