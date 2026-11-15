<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Application\Actions\Campaigns\DecideCampaignOrder;
use App\Models\UserCampaignOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CampaignOrderController
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending', 'approved', 'rejected', 'all'], true), 422);
        $query = UserCampaignOrder::query()->with('user:id,full_name,username,email')->latest();
        if ($status !== 'all') $query->where('status', $status);

        return response()->json(['data' => $query->paginate(30)]);
    }

    public function decide(Request $request, UserCampaignOrder $order, DecideCampaignOrder $decide): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $result = $decide->handle($request->user(), $order, $data['decision'], $data['note'] ?? null);

        return response()->json(['data' => $result, 'message' => $data['decision'] === 'rejected' ? 'Campaign rejected and top-up funds refunded.' : 'Campaign approved.']);
    }
}
