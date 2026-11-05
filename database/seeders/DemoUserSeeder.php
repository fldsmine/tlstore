<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $countries = ['NG', 'GH', 'KE', 'ZA', 'GB', 'US', 'CA', 'ZM', 'IN', 'AE', 'AU', 'NZ', 'UG', 'TZ', 'RW'];
        $currencies = ['NGN', 'GHS', 'KES', 'ZAR', 'GBP', 'USD', 'CAD', 'ZMW', 'INR', 'AED', 'AUD', 'NZD', 'UGX', 'TZS', 'RWF'];
        $rows = [];
		$imgList = ['avatar/user10.jpg','avatar/user08.jpg','avatar/user07.avif','avatar/user06.png','avatar/user05.png','avatar/user04.jpg','avatar/user03.webp','avatar/user02.jpg','avatar/user01.avif'];
        $password = (string) config('app.system_account_password');

        for ($number = 1; $number <= 15; $number++) {
            $username = sprintf('tl_demo%02d', $number);
            $rows[] = [
                'full_name' => sprintf('TrustLens User %02d', $number),
                'username' => $username,
                'email' => sprintf('demo.user%02d@tlstore.test', $number),
                'phone' => sprintf('+234800000%04d', $number),
                'email_verified_at' => $now,
                'password' => Hash::make($password),
                'country_code' => $countries[$number - 1],
                'preferred_currency' => $currencies[$number - 1],
                'role' => $number === 2 ? 'owner' : 'reviewer',
                'status' => $number === 15 ? 'restricted' : 'active',
                'avatar' => $imgList[$number % count($imgList)],
                'cover_image' => 'avatar/bg.jpg',
                'is_banned' => $number === 15,
                'ban_reason' => $number === 15 ? 'Demo-only banned account for access-control testing.' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('users')->upsert(
            $rows,
            ['email'],
            [
                'full_name', 'username', 'phone', 'email_verified_at', 'password', 'country_code',
                'preferred_currency', 'role', 'status', 'avatar', 'cover_image', 'is_banned',
                'ban_reason', 'updated_at',
            ]
        );

        $users = DB::table('users')
            ->whereIn('email', array_column($rows, 'email'))
            ->get(['id', 'email', 'username']);

        foreach ($users as $user) {
            $number = (int) substr($user->email, 9, 2);
            $walletRows = [];
            foreach (['review', 'share', 'referral', 'bonus', 'topup_fund'] as $walletType) {
                $walletRows[] = [
                    'user_id' => $user->id,
                    'wallet_type' => $walletType,
                    'currency' => 'USD',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('wallets')->upsert(
                $walletRows,
                ['user_id', 'wallet_type', 'currency'],
                ['status', 'updated_at']
            );

            $referralCode = sprintf('TLDEMO%02d', $number);
            DB::table('referral_codes')->upsert([[
                'user_id' => $user->id,
                'code' => $referralCode,
                'status' => 'active',
                'expires_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['code'], ['user_id', 'status', 'updated_at']);

            if ($number <= 3) {
                $providers = ['google', 'facebook', 'telegram'];
                $provider = $providers[$number - 1];
                DB::table('social_identities')->upsert([[
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => 'demo-'.$provider.'-'.$user->username,
                    'provider_email' => $user->email,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['provider', 'provider_user_id'], ['user_id', 'provider_email', 'updated_at']);
            }

            if ($number <= 10) {
                $token = 'tlstore-demo-push-token-'.$user->username;
                $tokenHash = hash('sha256', $token);
                DB::table('user_devices')->upsert([[
                    'user_id' => $user->id,
                    'platform' => $number % 2 === 0 ? 'ios' : 'android',
                    'push_token' => $token,
                    'token_hash' => $tokenHash,
                    'last_seen_at' => $now,
                    'revoked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['token_hash'], ['user_id', 'platform', 'push_token', 'last_seen_at', 'revoked_at', 'updated_at']);
            }
        }
    }
}
