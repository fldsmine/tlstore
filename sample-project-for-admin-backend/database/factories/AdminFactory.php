<?php

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => AdminRole::REGULAR->value,
            'remember_token' => Str::random(10),
        ];
    }

    public function super(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AdminRole::SUPER->value,
        ]);
    }

    public function regular(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AdminRole::REGULAR->value,
        ]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => AdminRole::SUPPORT->value,
        ]);
    }
}
