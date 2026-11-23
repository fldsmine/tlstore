<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Admin accounts for the backoffice. Password is a throwaway per factory call;
 * tests should assert against auth flows rather than the generated value.
 */
class AdminFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make(fake()->password(14)),
            'role' => 'admin',
            'status' => 'active',
            'last_login_at' => null,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(['role' => 'super_admin']);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}
