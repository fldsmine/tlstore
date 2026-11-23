<?php

namespace App\Services;

use App\Enums\OtpType;
use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    private const int MAX_ATTEMPTS = 5;

    private const int VERIFY_LIMIT = 10;

    private const int VERIFY_DECAY = 600;

    public function send(?User $user, ?string $identifier, OtpType $type): Otp
    {
        Otp::query()
            ->where('type', $type->value)
            ->whereNull('used_at')
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->when(! $user && $identifier, fn ($q) => $q->where('identifier', $identifier))
            ->update(['used_at' => now()]);

        $otp = Otp::create([
            'user_id' => $user?->id,
            'identifier' => $identifier,

            'code' => app()->environment(['local', 'testing']) ? '123456' : (string) random_int(100000, 999999),
            'type' => $type->value,
            'expires_at' => now()->addMinutes(10),
        ]);

        $recipient = $user?->email ?? $identifier;

        if ($recipient) {
            try {
                Mail::to($recipient)->send(new OtpMail($otp->code, $type, $user?->first_name));
            } catch (\Throwable $e) {
                Log::warning('OTP mail dispatch failed: '.$e->getMessage());
            }
        }

        return $otp;
    }

    public function verify(?User $user, ?string $identifier, OtpType $type, string $code): bool
    {
        $rlKey = 'otp:'.$type->value.':'.($user?->id ?? $identifier ?? request()->ip());

        if (RateLimiter::tooManyAttempts($rlKey, self::VERIFY_LIMIT)) {
            return false;
        }

        $otp = Otp::query()
            ->where('type', $type->value)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->when(! $user && $identifier, fn ($q) => $q->where('identifier', $identifier))
            ->latest()
            ->first();

        if (! $otp || ! hash_equals((string) $otp->code, $code)) {
            RateLimiter::hit($rlKey, self::VERIFY_DECAY);

            if ($otp) {
                $otp->increment('attempts');
                if ((int) $otp->attempts >= self::MAX_ATTEMPTS) {
                    $otp->forceFill(['used_at' => now()])->save();
                }
            }

            return false;
        }

        $consumed = Otp::query()->whereKey($otp->id)->whereNull('used_at')->update(['used_at' => now()]);

        if ($consumed === 0) {
            return false;
        }

        RateLimiter::clear($rlKey);

        return true;
    }
}
