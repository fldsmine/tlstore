<?php

namespace Database\Factories;

use App\Models\AllocatedNumber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllocatedNumber>
 */
class AllocatedNumberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'prefix' => AllocatedNumber::randomPrefix(),
            'suffix' => AllocatedNumber::randomSuffix(),
            'source' => 'package',
            'expires_at' => null,
            'additional_minute_plan_id' => null,
        ];
    }

    public function expiring(int $days = 30): static
    {
        return $this->state(fn () => [
            'source' => 'additional',
            'expires_at' => now()->addDays($days),
        ]);
    }
}
