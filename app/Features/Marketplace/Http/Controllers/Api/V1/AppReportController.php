<?php

namespace App\Features\Marketplace\Http\Controllers\Api\V1;

use App\Models\AppReport;
use App\Models\AppReviewCampaign;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AppReportController
{
    public function store(Request $request, AppReviewCampaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', 'string', Rule::in(['misleading_listing', 'broken_store_link', 'inappropriate_content', 'impersonation_or_copyright', 'other'])],
            'details' => ['nullable', 'string', 'max:3000'],
        ]);

        $user = $request->user();
        $alreadyOpen = AppReport::query()
            ->where('reporter_user_id', $user->id)
            ->where('app_review_campaign_id', $campaign->id)
            ->whereIn('status', ['pending', 'under_review'])
            ->exists();

        if ($alreadyOpen) {
            return response()->json(['message' => 'You already have an open report for this app listing.'], 422);
        }

        $report = AppReport::query()->create([
            'reporter_user_id' => $user->id,
            'app_id' => $campaign->app_id,
            'app_review_campaign_id' => $campaign->id,
            'reason_code' => $data['reason_code'],
            'details' => $data['details'] ?? null,
        ]);

        return response()->json(['message' => 'Thank you. Your report was sent to the review team.', 'data' => ['id' => $report->id, 'status' => $report->status]], 201);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(['pending', 'under_review', 'resolved', 'rejected'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $reports = AppReport::query()->with([
            'app:id,name,store,developer,app_icon',
            'campaign:id,campaign_key,app_id,status',
            'reporter:id,full_name,username,email',
            'reviewerAdmin:id,full_name,email',
        ])->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->paginate($data['per_page'] ?? 30);

        return response()->json(['data' => $reports]);
    }

    public function adminUpdate(Request $request, AppReport $report): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'resolved', 'rejected'])],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $before = $report->only(['status', 'admin_notes', 'reviewed_by_admin_id', 'reviewed_at']);
        $report->forceFill([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_by_admin_id' => $data['status'] === 'under_review' ? null : $request->user()->id,
            'reviewed_at' => $data['status'] === 'under_review' ? null : now(),
        ])->save();

        AuditLog::query()->create([
            'actor_admin_id' => $request->user()->id,
            'action' => 'app_report.updated',
            'entity_type' => 'app_report',
            'entity_id' => (string) $report->id,
            'before_values' => $before,
            'after_values' => $report->only(['status', 'admin_notes', 'reviewed_by_admin_id', 'reviewed_at']),
            'created_at' => now(),
        ]);

        return response()->json(['data' => $report->fresh()->load(['app:id,name,store', 'reporter:id,full_name,email', 'reviewerAdmin:id,full_name'])]);
    }
}
