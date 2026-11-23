<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AllocatedNumberResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Beneficiaries: the user's allocated call numbers (package Call IDs and
 * purchased additional minutes numbers). Read-only here — numbers are granted
 * by package activation and minute purchases, exactly like the web.
 */
class NumberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $numbers = $request->user()->allocatedNumbers()
            ->latest('created_at')
            ->get()
            ->map(function ($number) {
                $number->is_expired = $number->expires_at !== null && $number->expires_at->isPast();

                return $number;
            });

        return ApiResponse::success('Numbers retrieved successfully.', [
            'numbers' => AllocatedNumberResource::collection($numbers),
        ]);
    }
}
