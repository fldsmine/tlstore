<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request, OtpService $otp): JsonResponse
    {
        $email = (string) $request->validated('email');

        $key = 'otp-reset-send:'.Str::lower($email).'|'.$request->ip();

        // Same budget as the web flow (3 sends / 10 minutes) — but always
        // answer identically so the endpoint can't be used to probe accounts.
        if (! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 600);

            $user = User::query()->where('email', $email)->first();

            if ($user) {
                $otp->send(null, $email, OtpType::PASSWORD_RESET);
            }
        }

        return ApiResponse::success('If that email exists, we sent a reset code.');
    }

    public function reset(ResetPasswordRequest $request, OtpService $otp): JsonResponse
    {
        $email = (string) $request->validated('email');

        if (! $otp->verify(null, $email, OtpType::PASSWORD_RESET, (string) $request->validated('code'))) {
            return ApiResponse::error('That code is invalid or has expired.', 422, [
                'code' => ['That code is invalid or has expired.'],
            ]);
        }

        $user = User::query()->where('email', $email)->firstOrFail();

        $user->forceFill(['password' => $request->validated('password')])->save();

        // A reset password invalidates every previously issued device token.
        $user->tokens()->delete();

        return ApiResponse::success('Password updated — please sign in.');
    }
}
