<?php

namespace App\Features\Identity\Http\Controllers\Api\V1;

use App\Application\Security\TotpAuthenticator;
use App\Features\Identity\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class TwoFactorChallengeController
{
    public function verify(Request $request, TotpAuthenticator $totp): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'min:6', 'max:20']]);
        /** @var User $user */
        $user = $request->user();
        $challenge = $user->currentAccessToken();
        if (! $challenge instanceof PersonalAccessToken
            || ! $user->two_factor
            || ! in_array('two_factor_challenge', $challenge->abilities, true)
            || ! $challenge->created_at
            || $challenge->created_at->lt(now()->subMinutes(10))) {
            return response()->json(['message' => 'The two-factor login challenge expired. Sign in again.'], 401);
        }

        $valid = $totp->verifyAndConsume($user, $data['code']);
        if (! $valid) {
            return response()->json(['message' => 'That authenticator or recovery code is invalid.'], 422);
        }

        $challenge->delete();
        $token = $user->createToken('api', ['*', 'two_factor_verified'])->plainTextToken;

        return response()->json([
            'data' => new UserResource($user->fresh()),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
