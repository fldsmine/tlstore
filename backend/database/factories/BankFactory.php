<?php

namespace Database\Factories;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company().' Bank',
            'code' => $this->faker->unique()->numerify('###'),
            'tag' => strtolower($this->faker->unique()->word()),
            'type' => 'commercial',
            'country' => 'NG',
            'is_active' => true,
        ];
    }
}
