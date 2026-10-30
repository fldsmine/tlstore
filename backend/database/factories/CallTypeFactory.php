<?php

namespace Database\Factories;

use App\Enums\CallType as CallTypeEnum;
use App\Models\CallType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallType>
 */
class CallTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = $this->faker->unique()->randomElement(CallTypeEnum::cases());

        return [
            'name' => $key->getName(),
            'key' => $key,
            'description' => $this->faker->sentence(),
            'color' => '#22c55e',
            'rate_per_minute' => 0.05,
            'claimable_percentage' => 50,
            'price' => 0,
            'agent_price' => 0,
            'foreign_price' => null,
            'foreign_agent_price' => null,
            'region' => 'nigeria',
            'monthly_share_minutes' => 0,
            'share_term_days' => 30,
            'max_daily_shareholder_earning' => 0,
            'share_purchase_prompt_minutes' => 90,
            'min_minutes' => 1,
            'max_minutes' => 60,
            'min_per_batch' => 1,
            'max_per_batch' => 10,
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
