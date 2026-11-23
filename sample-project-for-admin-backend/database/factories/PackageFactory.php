<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'price' => 10,
            'agent_price' => 8,
            'direct_referral_bonus' => 1,
            'indirect_referral_bonus' => 0.5,
            'tier_level' => $this->faker->unique()->numberBetween(1, 50),
            'daily_call_minutes' => 60,
            'monthly_share_minutes' => 0,
            'share_term_days' => 30,
            'max_daily_shareholder_earning' => 0,
            'call_id_limit' => 2,
            'features' => null,
            'is_featured' => false,
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
