<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Application\Actions\Moderation\DecideAdShare;
use App\Application\Actions\Moderation\DecideReviewSubmission;
use App\Application\Actions\Moderation\DecideManualDeposit;
use App\Application\Actions\Moderation\SettleWithdrawal;
use App\Models\AdShareEvent;
use App\Models\Admin;
use App\Models\ReviewSubmission;
use App\Models\ManualDepositSubmission;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ModerationController
{
    public function reviews(): JsonResponse
    {
        return response()->json(['data' => ReviewSubmission::query()
            ->with(['reviewer:id,full_name,username,email', 'task.campaign.app', 'answers.question'])
            ->where('status', 'pending_review')
            ->oldest('submitted_at')->paginate(30)]);
    }

    public function shares(): JsonResponse
    {
        return response()->json(['data' => AdShareEvent::query()
            ->with(['task.user:id,full_name,username,email', 'task.ad'])
            ->where('status', 'pending_validation')
            ->oldest('created_at')->paginate(30)]);
    }

    public function decideReview(Request $request, ReviewSubmission $submission, DecideReviewSubmission $decide): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason_code' => ['required_if:decision,rejected', 'nullable', 'string', 'max:64'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $result = $decide->handle($request->user(), $submission, $data['decision'], $data['reason_code'] ?? null, $data['reason'] ?? null);

        return response()->json(['data' => $result, 'message' => 'Review decision recorded.']);
    }

    public function decideShare(Request $request, AdShareEvent $event, DecideAdShare $decide): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason_code' => ['required_if:decision,rejected', 'nullable', 'string', 'max:64'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $result = $decide->handle($request->user(), $event, $data['decision'], $data['reason_code'] ?? null, $data['reason'] ?? null);

        return response()->json(['data' => $result, 'message' => 'Share decision recorded.']);
    }

    public function deposits(): JsonResponse
    {
        return response()->json(['data' => ManualDepositSubmission::query()
            ->with(['user:id,full_name,username,email', 'paymentIntent'])
            ->where('status', 'submitted')->oldest('submitted_at')->paginate(30)]);
    }

    public function decideDeposit(Request $request, ManualDepositSubmission $deposit, DecideManualDeposit $decide): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $result = $decide->handle($request->user(), $deposit, $data['decision'], $data['reason'] ?? null);

        return response()->json(['data' => $result, 'message' => 'Manual deposit decision recorded.']);
    }

    public function withdrawals(): JsonResponse
    {
        return response()->json(['data' => WithdrawalRequest::query()
            ->with(['user:id,full_name,username,email', 'wallet', 'option', 'userSubscription.plan.group'])
            ->where('status', 'pending')->oldest('requested_at')->paginate(30)]);
    }

    public function settleWithdrawal(Request $request, WithdrawalRequest $withdrawal, SettleWithdrawal $settle): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:paid,rejected'],
            'provider_reference' => ['nullable', 'string', 'max:191'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $result = $settle->handle($request->user(), $withdrawal, $data['decision'], $data['provider_reference'] ?? null, $data['reason'] ?? null);

        return response()->json(['data' => $result->load(['user:id,full_name,username,email', 'wallet', 'option', 'userSubscription.plan.group']), 'message' => 'Withdrawal settlement recorded.']);
    }

    public function depositProof(ManualDepositSubmission $deposit)
    {
        abort_unless($deposit->proof_storage_path && Storage::disk('local')->exists($deposit->proof_storage_path), 404);

        return Storage::disk('local')->download($deposit->proof_storage_path);
    }

    public function shareProof(AdShareEvent $event)
    {
        $path = $event->evidence['screenshot_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }
}
