<?php

namespace App\Services\Auth;

use App\Enums\OtpType;
use App\Enums\ReferralStatus;
use App\Models\Referral;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared registration pipeline for the web (Livewire) and API clients.
 * Extracted from App\Livewire\Auth\Register so both clients share one
 * source of truth for account creation rules.
 */
class RegistrationService
{
    public function __construct(private OtpService $otp) {}

    public static function isOpen(): bool
    {
        return (bool) config('settings.app.enable_registration', true);
    }

    /**
     * Create the user (and referral record when a referrer was given).
     *
     * Expects pre-validated data: first_name, last_name, username, email,
     * phone, country (ISO2), password, optional referral_code.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        if (! static::isOpen()) {
            throw ValidationException::withMessages(['email' => 'New registrations are closed right now.']);
        }

        $referrer = filled($data['referral_code'] ?? null)
            ? User::query()->where('username', $data['referral_code'])->first()
            : null;

        if ($referrer && ! $referrer->isNigerian() && strtoupper((string) $data['country']) === 'NG') {
            throw ValidationException::withMessages([
                'country' => 'Your referrer is registered outside Nigeria and can only refer users outside Nigeria. Choose a different country, or sign up without a referral.',
            ]);
        }

        $verifyEnabled = (bool) config('settings.app.enable_email_verification');

        return DB::transaction(function () use ($data, $referrer, $verifyEnabled): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => strtoupper((string) $data['country']),
                'password' => $data['password'],
                'referrer_id' => $referrer?->id,
                'email_verified_at' => $verifyEnabled ? null : now(),
            ]);

            if ($referrer) {
                Referral::create([
                    'referrer_id' => $referrer->id,
                    'referee_id' => $user->id,
                    'status' => ReferralStatus::PENDING->value,
                ]);
            }

            return $user;
        });
    }

    /**
     * Kick off account verification when the feature is enabled.
     *
     * Returns the delivery channel: 'telegram' when the registration came
     * from the Mini App and the bot managed to DM the code (email is still
     * sent as backup), 'email' otherwise, or null when verification is off.
     *
     * @return 'telegram'|'email'|null
     */
    public function dispatchVerification(User $user, ?string $initData = null): ?string
    {
        if (! (bool) config('settings.app.enable_email_verification')) {
            return null;
        }

        $otp = $this->otp->send($user, null, OtpType::EMAIL_VERIFICATION);

        $channel = 'email';

        if (filled($initData)) {
            try {
                $telegramUser = app(TelegramAuthService::class)->validate((string) $initData);

                $sent = app(TelegramBotClient::class)->sendMessage(
                    $telegramUser->id,
                    "Nextel Connect — your account verification code is {$otp->code}. ".
                    'The Mini App verifies automatically; enter this code anywhere else you sign up.'
                );

                if ($sent) {
                    // One-shot intent: proves the registration presented this
                    // Telegram session, letting the Mini App auto-verify.
                    Cache::put(self::telegramIntentKey($telegramUser->id), $user->id, now()->addMinutes(10));
                    $channel = 'telegram';
                }
            } catch (\Throwable) {
                // Bot unconfigured, initData invalid or DM impossible (user
                // never started the bot) — email remains the channel.
            }
        }

        return $channel;
    }

    /** Cache key for the pending Telegram verification intent. */
    public static function telegramIntentKey(int $telegramUserId): string
    {
        return 'telegram-verify:'.$telegramUserId;
    }
}
