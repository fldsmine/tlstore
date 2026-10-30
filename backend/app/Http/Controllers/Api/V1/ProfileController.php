<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AvatarRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\ImageCompressionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->validated();

        $updates = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'phone' => trim((string) ($data['phone'] ?? '')) !== '' ? trim((string) $data['phone']) : null,
            'telegram' => $data['telegram'] ?? null,
            'biography' => $data['biography'] ?? null,
        ];

        // Currency is only touched when explicitly sent — older clients that
        // don't know the field must not wipe the preference. USD stores null,
        // same convention as the web switcher.
        if ($request->has('display_currency')) {
            $currency = strtoupper(trim((string) ($data['display_currency'] ?? '')));
            $updates['display_currency'] = $currency !== '' && $currency !== 'USD' ? $currency : null;
        }

        $request->user()->update($updates);

        return ApiResponse::success('Profile updated successfully.', [
            'user' => new UserResource($request->user()->load('package', 'referrer')),
        ]);
    }

    public function avatar(AvatarRequest $request, ImageCompressionService $images): JsonResponse
    {
        $path = $images->compressAndStore($request->file('photo'), 'avatars', null, 82, 512, 512);

        $request->user()->update(['avatar' => $path]);

        return ApiResponse::success('Avatar updated successfully.', [
            'user' => new UserResource($request->user()->load('package', 'referrer')),
        ]);
    }
}
