<?php

namespace App\Application\Actions\Identity;

use App\Enums\UserStatus;
use App\Enums\WalletType;
use App\Exceptions\AccountUnavailable;
use App\Models\Country;
use App\Models\ReferralCode;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SocialIdentityVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SocialLogin
{
    public function __construct(private readonly SocialIdentityVerifier $verifier)
    {
    }

    public function handle(string $provider, array $credentials, ?string $countryCode, ?string $preferredCurrency): User
    {
        $profile = $this->verifier->verify($provider, $credentials);

        return DB::transaction(function () use ($provider, $profile, $countryCode, $preferredCurrency): User {
            $identity = SocialIdentity::query()->where('provider', $provider)
                ->where('provider_user_id', $profile['provider_user_id'])->first();
            if ($identity) {
                $user = $identity->user;
                if ($user?->trashed()) {
                    throw new AccountUnavailable('deleted', 'This account has been deleted and is scheduled for permanent removal after 30 days.');
                }
                if (! $user || $user->status !== UserStatus::Active || $user->is_banned) {
                    throw new AccountUnavailable('banned', $user?->ban_reason ?: 'This account is currently unavailable.');
                }
                return $user;
            }

            $user = null;
            if ($profile['email'] && $profile['email_verified']) {
                $user = User::withTrashed()->where('email', $profile['email'])->first();
                if ($user?->trashed()) {
                    throw new AccountUnavailable('deleted', 'This account has been deleted and is scheduled for permanent removal after 30 days.');
                }
                if ($user && ($user->status !== UserStatus::Active || $user->is_banned)) {
                    throw new AccountUnavailable('banned', $user->ban_reason ?: 'This account is currently unavailable.');
                }
            } elseif ($profile['email'] && User::query()->where('email', $profile['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'Sign in to the existing account before linking this social provider.']);
            }

            if (! $user) {
                if (! $countryCode) {
                    throw ValidationException::withMessages(['country_code' => 'Select a country to create a new account.']);
                }
                $country = Country::query()->where('code', strtoupper($countryCode))->where('is_available', true)->first();
                if (! $country) {
                    throw ValidationException::withMessages(['country_code' => 'The selected country is unavailable.']);
                }

                $username = $this->uniqueUsername($profile['username'] ?: ($profile['email'] ? Str::before($profile['email'], '@') : $profile['full_name']));
                $user = User::query()->create([
                    'full_name' => $profile['full_name'] ?: $username,
                    'username' => $username,
                    'email' => $profile['email'],
                    'email_verified_at' => $profile['email_verified'] ? now() : null,
                    'country_code' => $country->code,
                    'preferred_currency' => strtoupper($preferredCurrency ?: $country->default_currency ?: 'USD'),
                    'wallet_currency' => strtoupper($preferredCurrency ?: $country->default_currency ?: 'USD'),
                    'role' => 'reviewer', 'status' => 'active',
                    'avatar' => $profile['avatar'],
                ]);

                foreach (WalletType::accountWalletTypes() as $type) {
                    Wallet::query()->create([
                        'user_id' => $user->id, 'wallet_type' => $type,
                        'currency' => $user->wallet_currency, 'status' => 'active',
                    ]);
                }
                ReferralCode::query()->create([
                    'user_id' => $user->id, 'code' => Str::upper(Str::random(5)), 'status' => 'active',
                ]);
            }

            SocialIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $profile['provider_user_id'],
                'provider_email' => $profile['email'],
            ]);

            return $user;
        });
    }

    private function uniqueUsername(?string $candidate): string
    {
        $base = Str::slug($candidate ?: 'member', '_');
        $base = substr($base, 0, 70) ?: 'member';
        $username = $base;
        $suffix = 1;
        while (User::query()->where('username', $username)->exists()) {
            $username = substr($base, 0, 70).$suffix++;
        }

        return $username;
    }
}
