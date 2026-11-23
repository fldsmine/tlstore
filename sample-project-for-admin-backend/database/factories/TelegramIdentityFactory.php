<?php

namespace Database\Factories;

use App\Models\TelegramIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramIdentity>
 */
class TelegramIdentityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'telegram_user_id' => fake()->unique()->numberBetween(100_000, 2_000_000_000),
            'username' => fake()->unique()->userName(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'language_code' => 'en',
        ];
    }
}
