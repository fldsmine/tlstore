<?php

namespace App\Features\Identity\Http\Controllers\Api\V1;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AdminAuthController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::query()->where('email', $credentials['email'])->first();
        if (! $admin || ! Hash::check($credentials['password'], $admin->password) || $admin->status !== 'active') {
            return response()->json(['message' => 'The provided credentials are invalid or the admin account is unavailable.'], 422);
        }

        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'data' => ['id' => $admin->id, 'full_name' => $admin->full_name, 'email' => $admin->email, 'role' => $admin->role],
            'token' => $admin->createToken('admin-api')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json(['data' => [
            'id' => $admin->id,
            'full_name' => $admin->full_name,
            'email' => $admin->email,
            'role' => $admin->role,
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
