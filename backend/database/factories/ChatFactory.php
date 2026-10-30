<?php

namespace Database\Factories;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chat>
 */
class ChatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'agent_id' => User::factory()->state(['is_agent' => true]),
            'last_message_at' => now(),
        ];
    }
}
