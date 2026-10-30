<?php

namespace Database\Factories;

use App\Models\AdditionalMinutePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdditionalMinutePlan>
 */
class AdditionalMinutePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'price' => 5,
            'agent_price' => 4,
            'daily_call_minutes' => 15,
            'share_minutes' => 0,
            'duration_days' => 30,
            'paylater_duration_days' => 14,
            'min_earning_rate' => 0.05,
            'call_id_bonus' => 0,
            'referral_bonus' => 0,
            'features' => null,
            'is_featured' => false,
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
