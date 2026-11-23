<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoginChannel;
use App\Enums\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TelegramInitDataRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Otp;
use App\Models\TelegramIdentity;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\Auth\RegistrationService;
use App\Services\Telegram\TelegramAuthService;
use App\Services\Telegram\TelegramLinkService;
use App\Services\Telegram\TelegramUser;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Telegram Mini App authentication. Thin by design: signature validation and
 * freshness/replay rules live in TelegramAuthService; account state rules
 * (suspension) are reused from the existing LoginService. The Mini App
 * itself is a normal Sanctum client once it holds a token.
 */
class TelegramAuthController extends Controller
{
    public function __construct(
        private readonly TelegramAuthService $telegram,
        private readonly LoginService $logins,
    ) {}

    /**
     * Exchange validated initData for a bearer token — but only when the
     * Telegram account is already linked to a Nextel user. An unlinked
     * account gets `linked: false` so the Mini App can present the regular
     * login screen (existing /login endpoint) and then /telegram/link.
     */
    public function auth(TelegramInitDataRequest $request): JsonResponse
    {
        $telegramUser = $this->telegram->validate((string) $request->validated('init_data'));

        $identity = TelegramIdentity::query()
            ->where('telegram_user_id', $telegramUser->id)
            ->first();

        if (! $identity) {
            // Not sensitive (no token, no binding) — leave the initData
            // unconsumed so the same session can still be used for /link
            // right after a password login.
            return ApiResponse::success('This Telegram account is not linked to a Nextel account yet.', [
                'linked' => false,
                'telegram_user' => $this->telegramPayload($telegramUser),
            ]);
        }

        // Mark this initData consumed before minting a token — a captured
        // payload can never be replayed to mint additional tokens.
        $this->telegram->consume((string) $request->validated('init_data'));

        $identity->update(array_filter([
            'username' => $telegramUser->username,
            'first_name' => $telegramUser->firstName,
            'last_name' => $telegramUser->lastName,
            'language_code' => $telegramUser->languageCode,
        ], fn ($value) => $value !== null));

        $user = $identity->user()->firstOrFail();

        if ($this->logins->isSuspended($user)) {
            return ApiResponse::error('This account has been suspended. Please contact support.', 403);
        }

        $this->logins->touchLastLogin($user, LoginChannel::TELEGRAM);

        return ApiResponse::success('Telegram login successful.', [
            'linked' => true,
            'token' => $user->createToken(TelegramAuthService::TOKEN_NAME)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('package', 'referrer', 'telegramIdentity')),
        ]);
    }

    /**
     * Link the validated Telegram account to the authenticated user. Requires
     * a normal Sanctum session (e.g. from /login), which proves ownership of
     * the Nextel account before any Telegram identity is attached to it.
     */
    public function link(TelegramInitDataRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $telegramUser = $this->telegram->validate((string) $request->validated('init_data'));

        $existing = TelegramIdentity::query()->where('telegram_user_id', $telegramUser->id)->first();

        if ($existing && $existing->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'init_data' => 'This Telegram account is already linked to another Nextel account.'],
            );
        }

        if (! $existing && $user->telegramIdentity()->exists()) {
            throw ValidationException::withMessages([
                'init_data' => 'Your account is already linked to a different Telegram account. Unlink it first.'],
            );
        }

        // Bind happens now — consume the initData so it cannot be replayed
        // to re-link (e.g. against another session).
        $this->telegram->consume((string) $request->validated('init_data'));

        $identity = $user->telegramIdentity()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'telegram_user_id' => $telegramUser->id,
                'username' => $telegramUser->username,
                'first_name' => $telegramUser->firstName,
                'last_name' => $telegramUser->lastName,
                'language_code' => $telegramUser->languageCode,
            ],
        );

        return ApiResponse::success('Telegram account linked successfully.', [
            'telegram' => $this->identityPayload($identity),
        ]);
    }

    /**
     * Remove the Telegram link from the authenticated account. Sessions the link
     * minted are revoked too, so a stale Mini App token cannot outlive the binding.
     */
    public function unlink(Request $request, TelegramLinkService $links): JsonResponse
    {
        $links->unlink($request->user());

        return ApiResponse::success('Telegram account unlinked.');
    }

    /**
     * Auto-verify the account email for Mini App registrations: the code was
     * delivered to this Telegram session, so presenting the same validated
     * initData (plus the bearer token issued at registration) proves control
     * of the channel — no manual code entry needed.
     */
    public function verify(TelegramInitDataRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return ApiResponse::success('Email already verified.', ['verified' => true]);
        }

        $telegramUser = $this->telegram->validate((string) $request->validated('init_data'));

        $intentKey = RegistrationService::telegramIntentKey($telegramUser->id);
        $intent = Cache::get($intentKey);

        if ($intent === null || (string) $intent !== (string) $user->id) {
            return ApiResponse::error(
                'No Telegram verification is pending for this account — enter the code from your Telegram message or email instead.',
                422,
            );
        }

        Cache::forget($intentKey);

        Otp::query()
            ->where('user_id', $user->id)
            ->where('type', OtpType::EMAIL_VERIFICATION->value)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return ApiResponse::success('Email verified via Telegram.', [
            'verified' => true,
            'user' => new UserResource($user->refresh()),
        ]);
    }

    /** @return array<string, mixed> */
    private function telegramPayload(TelegramUser $telegramUser): array
    {
        return [
            'id' => $telegramUser->id,
            'username' => $telegramUser->username,
            'first_name' => $telegramUser->firstName,
            'last_name' => $telegramUser->lastName,
        ];
    }

    /** @return array<string, mixed> */
    private function identityPayload(TelegramIdentity $identity): array
    {
        return [
            'username' => $identity->username,
            'first_name' => $identity->first_name,
            'last_name' => $identity->last_name,
            'linked_at' => $identity->created_at?->toIso8601String(),
        ];
    }
}
