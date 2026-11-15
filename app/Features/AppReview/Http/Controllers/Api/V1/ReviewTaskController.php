<?php

namespace App\Features\AppReview\Http\Controllers\Api\V1;

use App\Application\Actions\Reviews\ClaimReviewTask;
use App\Application\Actions\Reviews\SubmitReview;
use App\Models\AppReviewCampaign;
use App\Models\ReviewTask;
use App\Models\ReviewSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReviewTaskController
{
    public function claim(Request $request, AppReviewCampaign $campaign, ClaimReviewTask $claim): JsonResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:100']]);
        $task = $claim->handle($request->user(), $campaign, $data['idempotency_key']);

        return response()->json(['data' => $task], 201);
    }

    public function submit(Request $request, ReviewTask $task, SubmitReview $submit): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'distinct'],
            'answers.*.value' => ['required'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:7200'],
        ]);
        $submission = $submit->handle($request->user(), $task, $data['answers'], $data['duration_seconds'] ?? null);

        return response()->json(['data' => $submission, 'message' => 'Review submitted and awaiting admin approval.'], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => ReviewTask::query()->with(['campaign.app.screenshots', 'templateVersion.questions'])
            ->where('reviewer_id', $request->user()->id)
            ->whereDoesntHave('submission')
            ->whereIn('status', ['reserved', 'in_progress', 'assigned'])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('claimed_at')->paginate(30)]);
    }

    public function submissions(Request $request): JsonResponse
    {
        return response()->json(['data' => ReviewSubmission::query()->with(['task.campaign.app', 'answers.question', 'moderationDecisions'])
            ->where('reviewer_id', $request->user()->id)->latest('submitted_at')->paginate(30)]);
    }
}
