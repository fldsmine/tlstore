<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\ChangePinRequest;
use App\Http\Requests\Api\V1\ResetPinRequest;
use App\Http\Requests\Api\V1\SetupPinRequest;
use App\Http\Requests\Api\V1\VerifyEmailRequest;
use App\Services\OtpService;
use App\Services\TokenRedemptionService;
use App\Support\ApiResponse;
use App\Support\TransactionPinGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AccountController extends Controller
{
    public function verifyEmail(VerifyEmailRequest $request, OtpService $otp): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return ApiResponse::success('Email already verified.');
        }

        if (! $otp->verify($user, null, OtpType::EMAIL_VERIFICATION, (string) $request->validated('code'))) {
            return ApiResponse::error('That code is invalid or has expired.', 422, [
                'code' => ['That code is invalid or has expired.'],
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return ApiResponse::success('Email verified successfully.');
    }

    public function resendEmailCode(Request $request, OtpService $otp): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return ApiResponse::success('Email already verified.');
        }

        // Same budget as the web flow: 3 resends per minute.
        $key = 'otp-send:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return ApiResponse::error('Please wait a moment before requesting another code.', 429)
                ->withHeaders(['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, 60);
        $otp->send($user, null, OtpType::EMAIL_VERIFICATION);

        return ApiResponse::success('A new code has been sent.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => Hash::make($request->validated('password'))]);

        // Revoke every OTHER device token; the current session stays valid.
        $user->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()->id)
            ->delete();

        return ApiResponse::success('Password changed successfully.');
    }

    public function setupPin(SetupPinRequest $request): JsonResponse
    {
        $user = $request->user();

        if (filled($user->transaction_pin)) {
            return ApiResponse::error('A transaction PIN is already set. Use the change PIN flow.', 422);
        }

        $user->forceFill(['transaction_pin' => $request->validated('pin')])->save();

        return ApiResponse::created('Transaction PIN set successfully.');
    }

    public function changePin(ChangePinRequest $request, TransactionPinGuard $pins): JsonResponse
    {
        $user = $request->user();

        if ($pins->throttled($user)) {
            return ApiResponse::error('Too many incorrect attempts. Try again in '.$pins->availableIn($user).' seconds.', 429)
                ->withHeaders(['Retry-After' => (string) $pins->availableIn($user)]);
        }

        if (! $pins->verify($user, (string) $request->validated('current_pin'))) {
            return ApiResponse::error('Your transaction PIN is incorrect.', 422, [
                'current_pin' => ['Your transaction PIN is incorrect.'],
            ]);
        }

        $user->update(['transaction_pin' => $request->validated('pin')]);

        return ApiResponse::success('Transaction PIN changed successfully.');
    }

    public function resetPin(ResetPinRequest $request, TokenRedemptionService $redemption): JsonResponse
    {
        $redemption->resetPin($request->user(), (string) $request->validated('token'), (string) $request->validated('pin'));

        return ApiResponse::success('Transaction PIN reset successfully.');
    }
}
