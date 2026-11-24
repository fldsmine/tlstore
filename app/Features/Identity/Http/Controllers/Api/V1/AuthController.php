<?php

namespace App\Features\Identity\Http\Controllers\Api\V1;

use App\Application\Actions\Identity\RegisterUser;
use App\Application\Actions\Identity\UpdateProfile;
use App\Application\Actions\Identity\SocialLogin;
use App\Application\Security\SecurityPinHasher;
use App\Application\Security\TotpAuthenticator;
use App\Enums\UserStatus;
use App\Exceptions\AccountUnavailable;
use App\Features\Identity\Http\Resources\UserResource;
use App\Models\Country;
use App\Models\User;
use App\Services\TelegramBotUsername;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class AuthController
{
    public function register(Request $request, RegisterUser $registerUser): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'country_code' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'preferred_currency' => ['nullable', 'string', 'size:3'],
            'referral_code' => ['nullable', 'string', 'max:64'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        if (! Country::query()->where('code', $data['country_code'])->where('is_available', true)->exists()) {
            return response()->json(['message' => 'The selected country is unavailable.'], 422);
        }

        $user = $registerUser->handle($data);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required_without:email', 'nullable', 'string', 'max:191'],
            // Keep accepting email for older clients while newer clients send
            // the email-or-username identifier explicitly.
            'email' => ['required_without:identifier', 'nullable', 'string', 'max:191'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim((string) ($credentials['identifier'] ?? $credentials['email'] ?? ''));
        $userQuery = \App\Models\User::withTrashed();
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $userQuery->where('email', $identifier)->first()
            : $userQuery->where('username', $identifier)->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password ?? '')) {
            return response()->json(['message' => 'The provided credentials are invalid or the account is unavailable.'], 422);
        }

        if ($user->trashed()) {
            return response()->json([
                'message' => 'This account has been deleted.',
                'account_state' => 'deleted',
                'account_reason' => 'This account is scheduled for permanent deletion 30 days after it was deleted.',
                'support_email' => config('trustlens.support_email'),
            ], 403);
        }

        if ($user->status !== UserStatus::Active || $user->is_banned) {
            return response()->json([
                'message' => 'This account is currently unavailable.',
                'account_state' => 'banned',
                'account_reason' => $user->ban_reason ?: 'Contact support for help with your account.',
                'support_email' => config('trustlens.support_email'),
            ], 403);
        }

        return $this->authenticatedResponse($user);
    }

    public function social(Request $request, string $provider, SocialLogin $socialLogin): JsonResponse
    {
        abort_unless(in_array($provider, ['google', 'facebook', 'telegram'], true), 404);
        $rules = [
            'country_code' => ['nullable', 'string', 'size:2'],
            'preferred_currency' => ['nullable', 'string', 'size:3'],
        ];
        if ($provider === 'google') {
            $rules['id_token'] = ['required', 'string'];
        } elseif ($provider === 'facebook') {
            $rules['access_token'] = ['required', 'string'];
        } else {
            $rules += [
                'telegram' => ['required', 'array'],
                'telegram.id' => ['required', 'integer'],
                'telegram.auth_date' => ['required', 'integer'],
                'telegram.hash' => ['required', 'string'],
            ];
        }
        $data = $request->validate($rules);
        $credentials = match ($provider) {
            'google' => ['id_token' => $data['id_token']],
            'facebook' => ['access_token' => $data['access_token']],
            'telegram' => $data['telegram'],
        };
        try {
            $user = $socialLogin->handle($provider, $credentials, $data['country_code'] ?? null, $data['preferred_currency'] ?? null);
        } catch (AccountUnavailable $error) {
            return response()->json([
                'message' => $error->getMessage(),
                'account_state' => $error->state,
                'account_reason' => $error->getMessage(),
                'support_email' => config('trustlens.support_email'),
            ], 403);
        }

        return $this->authenticatedResponse($user);
    }

    public function socialConfig(Request $request, TelegramBotUsername $telegramBot): JsonResponse
    {
        $botUsername = $telegramBot->get();

        return response()->json(['data' => [
            'google_server_client_id' => config('services.social.google_client_id'),
            'facebook_app_id' => config('services.social.facebook_app_id'),
            'telegram_login_url' => $botUsername && config('services.social.telegram_bot_token')
                  ? $request->getSchemeAndHttpHost().route('auth.telegram.widget', [], false)
                : null,
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function sendPasswordResetLink(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:191']]);
        PasswordBroker::sendResetLink(['email' => $data['email']]);

        // Keep the response identical for known and unknown addresses.
        return response()->json([
            'message' => 'If an active account uses that email, a password reset token has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $status = PasswordBroker::reset($data, function (\App\Models\User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json(['message' => 'The password reset token is invalid or expired.'], 422);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updateProfile(Request $request, UpdateProfile $updateProfile): UserResource
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:180'],
            'username' => ['sometimes', 'string', 'alpha_dash', 'min:3', 'max:80', 'unique:users,username,'.$request->user()->id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'unique:users,phone,'.$request->user()->id],
            'preferred_currency' => ['sometimes', 'string', 'size:3'],
        ]);

        return new UserResource($updateProfile->handle($request->user(), $data));
    }

    public function uploadMedia(Request $request, string $type): UserResource
    {
        abort_unless(in_array($type, ['avatar', 'cover'], true), 404);
        $data = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $user = $request->user();
        $column = $type === 'cover' ? 'cover_image' : 'avatar';
        $previous = $user->{$column};
        $path = $request->file('image')->store('users/'.$user->id.'/'.$type, 'public');
        $user->forceFill([$column => $path])->save();
        if ($previous && ! preg_match('#^https?://#i', $previous)) {
            Storage::disk('public')->delete(preg_replace('#^/?storage/#', '', $previous));
        }

        return new UserResource($user->refresh());
    }

    public function deleteMedia(Request $request, string $type): UserResource
    {
        abort_unless(in_array($type, ['avatar', 'cover'], true), 404);
        $user = $request->user();
        $column = $type === 'cover' ? 'cover_image' : 'avatar';
        $previous = $user->{$column};
        $user->forceFill([$column => null])->save();
        if ($previous && ! preg_match('#^https?://#i', $previous)) {
            Storage::disk('public')->delete(preg_replace('#^/?storage/#', '', $previous));
        }

        return new UserResource($user->refresh());
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password ?? '')) {
            return response()->json(['message' => 'The current password is incorrect.'], 422);
        }
        $request->user()->forceFill(['password' => $data['password']])->save();

        return response()->json(['message' => 'Password updated.']);
    }

    public function setSecurityPin(Request $request, SecurityPinHasher $pinHasher): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
            'current_pin' => ['nullable', 'digits:4'],
            'current_password' => ['nullable', 'string'],
        ]);
        $user = $request->user();
        if ($user->security_pin) {
            if (! $pinHasher->check((string) ($data['current_pin'] ?? ''), $user->security_pin)) {
                return response()->json(['message' => 'The current security PIN is incorrect.'], 422);
            }
        } elseif ($user->password && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
            return response()->json(['message' => 'Confirm your account password before setting a withdrawal PIN.'], 422);
        }

        $user->forceFill(['security_pin' => $pinHasher->make($data['pin'])])->save();

        return response()->json(['data' => new UserResource($user->refresh()), 'message' => 'Withdrawal PIN saved.']);
    }

    public function removeSecurityPin(Request $request, SecurityPinHasher $pinHasher): JsonResponse
    {
        $data = $request->validate(['current_pin' => ['required', 'digits:4']]);
        $user = $request->user();
        if (! $pinHasher->check($data['current_pin'], $user->security_pin)) {
            return response()->json(['message' => 'The current security PIN is incorrect.'], 422);
        }

        $user->forceFill(['security_pin' => null])->save();

        return response()->json(['data' => new UserResource($user->refresh()), 'message' => 'Withdrawal PIN removed.']);
    }

    public function startTwoFactorSetup(Request $request, TotpAuthenticator $totp): JsonResponse
    {
        $user = $request->user();
        if ($user->two_factor) {
            return response()->json(['message' => 'Authenticator verification is already enabled.'], 422);
        }

        $secret = $totp->createSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_setup_started_at' => now(),
            'two_factor_last_counter' => null,
        ])->save();

        return response()->json(['data' => [
            'secret' => $secret,
            'otpauth_uri' => $totp->provisioningUri($secret, $user->email),
            'expires_in_minutes' => 15,
        ]]);
    }

    public function confirmTwoFactorSetup(Request $request, TotpAuthenticator $totp): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var User $user */
        $user = $request->user();
        $secret = $user->two_factor_secret;
        if (! $secret || ! $user->two_factor_setup_started_at || $user->two_factor_setup_started_at->lt(now()->subMinutes(15))) {
            return response()->json(['message' => 'Authenticator setup expired. Start setup again.'], 422);
        }
        $counter = $totp->matchingCounter($secret, $data['code']);
        if ($counter === null) {
            return response()->json(['message' => 'That authenticator code is invalid. Check the current code and try again.'], 422);
        }

        $recoveryCodes = $totp->createRecoveryCodes();
        $user->forceFill([
            'two_factor' => true,
            'two_factor_enabled_at' => now(),
            'two_factor_last_counter' => $counter,
            'two_factor_setup_started_at' => null,
            'two_factor_recovery_codes' => $totp->hashRecoveryCodes($recoveryCodes),
        ])->save();
        $user->currentAccessToken()?->forceFill(['abilities' => ['*', 'two_factor_verified']])->save();

        return response()->json([
            'data' => new UserResource($user->refresh()),
            'recovery_codes' => $recoveryCodes,
            'message' => 'Authenticator verification enabled.',
        ]);
    }

    public function disableTwoFactor(Request $request, TotpAuthenticator $totp): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        /** @var User $user */
        $user = $request->user();
        if (! $user->two_factor || ! $totp->verifyAndConsume($user, $data['code'])) {
            return response()->json(['message' => 'The authenticator code is incorrect.'], 422);
        }

        $currentToken = $user->currentAccessToken();
        if ($currentToken) $user->tokens()->whereKeyNot($currentToken->getKey())->delete();
        $user->forceFill([
            'two_factor' => false,
            'two_factor_secret' => null,
            'two_factor_setup_started_at' => null,
            'two_factor_enabled_at' => null,
            'two_factor_last_counter' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
        $currentToken?->forceFill(['abilities' => ['*']])->save();

        return response()->json(['data' => new UserResource($user->refresh()), 'message' => 'Authenticator verification disabled.']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $data = $request->validate(['current_password' => ['nullable', 'string']]);
        $user = $request->user();
        if ($user->password && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
            return response()->json(['message' => 'The current password is incorrect.'], 422);
        }

        $user->tokens()->delete();
        $user->devices()->delete();
        $user->referralCodes()->update(['status' => 'revoked']);
        $user->delete();

        return response()->json([
            'message' => 'Your account is deleted and scheduled for permanent removal in 30 days.',
            'permanent_deletion_at' => $user->deleted_at?->copy()->addDays(30),
        ]);
    }

    private function authenticatedResponse(User $user): JsonResponse
    {
        if ($user->two_factor) {
            return response()->json([
                'two_factor_required' => true,
                'challenge_token' => $user->createToken('two-factor-challenge', ['two_factor_challenge'])->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in_seconds' => 600,
            ], 202);
        }

        return response()->json([
            'data' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }
}
