<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\CloudInboxMessage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The app's notification feed is the N-Cloud inbox (system notices are
 * stored there too). Users without a cloud line simply get an empty feed.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $paginator = $request->user()->cloudInbox()
            ->paginate((int) $request->input('per_page', 20));

        return ApiResponse::paginated($paginator, NotificationResource::class, 'Notifications retrieved successfully.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->cloudInbox()->unread()->count();

        return ApiResponse::success('Unread count retrieved.', ['unread_count' => $count]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $message = $request->user()->cloudInbox()->whereKey($notification)->firstOrFail();

        if ($message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return ApiResponse::success('Notification marked as read.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $updated = $request->user()->cloudInbox()->unread()->update(['read_at' => now()]);

        return ApiResponse::success('Notifications marked as read.', ['updated' => $updated]);
    }
}
