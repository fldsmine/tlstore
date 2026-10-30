<?php

namespace Database\Factories;

use App\Models\CallRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallRecord>
 */
class CallRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minutes = $this->faker->randomFloat(2, 0.5, 18);
        $rate = $this->faker->randomElement([0.02, 0.035, 0.05, 0.075]);

        return [
            'call_type_id' => null,
            'direction' => $this->faker->randomElement(['incoming', 'outgoing']),
            'counterparty' => '0'.$this->faker->numberBetween(700, 909).$this->faker->numerify('#######'),
            'duration_minutes' => $minutes,
            'rate_per_minute' => $rate,
            'earnings' => round($minutes * $rate, 4),
        ];
    }
}
