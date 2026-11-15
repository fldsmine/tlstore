<?php

namespace App\Application\Actions\Identity;

use App\Enums\WalletType;
use App\Models\Country;
use App\Models\ReferralCode;
use App\Models\ReferralAttribution;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RegisterUser
{
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $country = Country::query()->where('code', strtoupper($data['country_code']))->where('is_available', true)->firstOrFail();
            $referrerCode = null;
            if (! empty($data['referral_code'])) {
                $referrerCode = ReferralCode::query()->where('code', strtoupper($data['referral_code']))
                    ->where('status', 'active')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->first();
                if (! $referrerCode) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['referral_code' => 'The referral code is invalid or expired.']);
                }
            }

            $user = User::query()->create([
                'full_name' => $data['full_name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'country_code' => $country->code,
                'preferred_currency' => $data['preferred_currency'] ?? $country->default_currency ?? 'USD',
                'wallet_currency' => strtoupper($data['preferred_currency'] ?? $country->default_currency ?? 'USD'),
                'role' => 'reviewer',
                'status' => 'active',
            ]);

            foreach (WalletType::accountWalletTypes() as $type) {
                Wallet::query()->create([
                    'user_id' => $user->id,
                    'wallet_type' => $type,
                    'currency' => $user->wallet_currency,
                ]);
            }

            ReferralCode::query()->create([
                'user_id' => $user->id,
                'code' => Str::upper(Str::random(10)),
                'status' => 'active',
            ]);

            if ($referrerCode) {
                ReferralAttribution::query()->create([
                    'referral_code_id' => $referrerCode->id,
                    'referrer_user_id' => $referrerCode->user_id,
                    'referred_user_id' => $user->id,
                    'status' => 'attributed',
                    'attributed_at' => now(),
                ]);
            }

            return $user;
        });
    }
}
