<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnnouncementResource;
use App\Models\Announcement;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    /** Public — lets logged-out clients (and the app's login screen) show notices. */
    public function index(): JsonResponse
    {
        $announcements = Announcement::query()->live()->latest()->get();

        return ApiResponse::success('Announcements retrieved successfully.', [
            'announcements' => AnnouncementResource::collection($announcements),
        ]);
    }
}
