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
    public function reviews(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = ReviewSubmission::query()
            ->with(['reviewer:id,full_name,username,email', 'task.campaign.app', 'answers.question', 'riskSignals'])
            ->where('status', 'pending_review');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($rows) => $rows
                ->whereHas('reviewer', fn ($users) => $users->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('task.campaign.app', fn ($apps) => $apps->where('name', 'like', "%{$search}%")));
        }

        return response()->json(['data' => $query->oldest('submitted_at')->paginate($filters['per_page'] ?? 30)->appends($request->query())]);
    }

    public function shares(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = AdShareEvent::query()
            ->with(['task.user:id,full_name,username,email', 'task.ad', 'riskSignals'])
            ->where('status', 'pending_validation');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($rows) => $rows
                ->whereHas('task.user', fn ($users) => $users->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('task.ad', fn ($ads) => $ads->where('title', 'like', "%{$search}%"))
                ->orWhere('external_reference', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->oldest('created_at')->paginate($filters['per_page'] ?? 30)->appends($request->query())]);
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

    public function deposits(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = ManualDepositSubmission::query()
            ->with(['user:id,full_name,username,email', 'paymentIntent'])
            ->where('status', 'submitted');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($rows) => $rows
                ->whereHas('user', fn ($users) => $users->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhere('bank_reference', 'like', "%{$search}%")
                ->orWhereHas('paymentIntent', fn ($payments) => $payments->where('provider_reference', 'like', "%{$search}%")));
        }

        return response()->json(['data' => $query->oldest('submitted_at')->paginate($filters['per_page'] ?? 30)->appends($request->query())]);
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

    public function withdrawals(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = WithdrawalRequest::query()
            ->with(['user:id,full_name,username,email', 'wallet', 'option', 'userSubscription.plan.group'])
            ->where('status', 'pending');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($rows) => $rows
                ->whereHas('user', fn ($users) => $users->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('option', fn ($options) => $options->where('name', 'like', "%{$search}%"))
                ->orWhere('provider_reference', 'like', "%{$search}%"));
        }

        return response()->json(['data' => $query->oldest('requested_at')->paginate($filters['per_page'] ?? 30)->appends($request->query())]);
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
