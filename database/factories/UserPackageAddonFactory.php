<?php

namespace Database\Factories;

use App\Models\UserPackageAddon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserPackageAddon>
 */
class UserPackageAddonFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'features' => '',
        ];
    }
}
