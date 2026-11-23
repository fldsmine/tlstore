<?php

namespace Database\Factories;

use App\Enums\UserGender;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * NOTE: Model::shouldBeStrict() is on and Laravel's actingAs() clears
 * wasRecentlyCreated, so every column the app may read MUST be present on
 * factory-built instances — hence the exhaustive defaults below.
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Identity
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->unique()->numerify('080########'),
            'country' => 'NG',
            'display_currency' => null,
            'telegram' => null,
            'avatar' => null,
            'biography' => null,
            'gender' => fake()->randomElement(UserGender::values()),
            'status' => UserStatus::ACTIVE->value,

            // Agent / vendor flags
            'is_agent' => false,
            'is_special' => false,
            'withdrawals_disabled' => false,
            'show_on_agents_page' => false,

            // Referral + package
            'referrer_id' => null,
            'package_id' => null,
            'cloud_line_number' => null,

            // Wallets (USD)
            'shareholder_balance' => 0,
            'legacy_shareholder_balance' => 0,
            'ads_revenue_balance' => 0,
            'referral_bonus_balance' => 0,
            'reward_balance' => 0,
            'agent_balance' => 0,
            'cloud_call_balance' => 0,
            'cloud_sms_balance' => 0,
            'bills_balance' => 0,
            'lifetime_earnings' => 0,

            // Auth / security
            'password' => static::$password ??= Hash::make('Password123!'),
            'transaction_pin' => null,

            // Activity stamps
            'last_claim_at' => null,
            'last_login_at' => null,
            'last_seen_at' => null,
            'website_expires_at' => null,

            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function agent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_agent' => true,
            'show_on_agents_page' => true,
            'telegram' => 'https://t.me/'.($attributes['username'] ?? fake()->userName()),
        ]);
    }

    public function topEarner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'lifetime_earnings' => fake()->randomFloat(2, 2_000, 9_000),
            'shareholder_balance' => fake()->randomFloat(2, 200, 1_500),
        ]);
    }
}
