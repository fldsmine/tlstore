<?php

namespace App\Features\System\Http\Controllers\Api\V1;

use App\Models\Announcement;
use App\Models\UserAnnouncementDismissal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnnouncementController
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Announcement::query()->visibleNow()
                ->whereDoesntHave('dismissals', fn ($query) => $query->where('user_id', $request->user()->id))
                ->orderBy('display_type')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function dismiss(Request $request, Announcement $announcement): JsonResponse
    {
        if (! $announcement->is_active || ! $announcement->is_dismissible || (int) $announcement->display_type === 2) {
            return response()->json(['message' => 'This announcement cannot be dismissed.'], 422);
        }

        UserAnnouncementDismissal::query()->firstOrCreate(
            ['user_id' => $request->user()->id, 'announcement_id' => $announcement->id],
            ['dismissed_at' => now()],
        );

        return response()->json(['message' => 'Announcement dismissed.']);
    }
}
