<?php

namespace Database\Factories;

use App\Models\MinuteTopUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MinuteTopUp>
 */
class MinuteTopUpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'share_subscription_id' => null,
            'daily_call_minutes' => 15,
            'share_minutes' => 0,
            'expires_at' => now()->addDays(30),
            'share_expires_at' => null,
        ];
    }
}
