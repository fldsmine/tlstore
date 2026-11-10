<?php

namespace App\Features\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserSocialAccountController
{
    public function index(Request $request): JsonResponse
    {
        $accounts = $request->user()->socialAccounts()->get(['platform', 'handle'])
            ->mapWithKeys(fn ($account) => [$account->platform => $account->handle]);

        return response()->json(['data' => $accounts]);
    }

    public function store(Request $request, string $platform): JsonResponse
    {
        abort_unless(in_array($platform, ['telegram', 'facebook', 'youtube', 'instagram', 'tiktok', 'x', 'whatsapp'], true), 404);
        $data = $request->validate(['handle' => ['required', 'string', 'max:255']]);

        $account = DB::transaction(function () use ($request, $platform, $data) {
            $existing = $request->user()->socialAccounts()->where('platform', $platform)->lockForUpdate()->first();
            if ($existing && filled($existing->handle)) {
                throw ValidationException::withMessages(['handle' => 'This social account is already set and cannot be changed.']);
            }
            return $request->user()->socialAccounts()->updateOrCreate(
                ['platform' => $platform], ['handle' => trim($data['handle'])],
            );
        });

        return response()->json(['data' => ['platform' => $account->platform, 'handle' => $account->handle]], 201);
    }
}
