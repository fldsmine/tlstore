<?php

namespace Database\Factories;

use App\Enums\VtuStatus;
use App\Models\User;
use App\Models\VtuOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VtuOrder>
 */
class VtuOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ngn = (float) $this->faker->randomElement([50, 100, 200, 500]);

        return [
            'user_id' => User::factory(),
            'service' => VtuOrder::SERVICE_AIRTIME,
            'service_id' => $this->faker->randomElement(['mtn', 'glo', 'airtel', '9mobile']),
            'wallet' => 'bills',
            'variation_code' => null,
            'billers_code' => null,
            'phone' => '080'.$this->faker->numerify('########'),
            'amount_ngn' => $ngn,
            'amount_usd' => round($ngn / 1500, 4),
            'fee' => 0,
            'request_id' => VtuOrder::generateRequestId(),
            'external_id' => (string) $this->faker->numerify('##############'),
            'status' => VtuStatus::DELIVERED,
            'token' => null,
            'refunded' => false,
            'transaction_id' => null,
            'meta' => [],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => VtuStatus::PENDING, 'external_id' => null]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => VtuStatus::FAILED, 'refunded' => true]);
    }
}
