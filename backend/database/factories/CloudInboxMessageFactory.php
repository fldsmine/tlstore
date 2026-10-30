<?php

namespace Database\Factories;

use App\Models\CloudInboxMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloudInboxMessage>
 */
class CloudInboxMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => CloudInboxMessage::TYPE_SYSTEM,
            'sender_name' => 'Nextel',
            'sender_number' => '+16985550100',
            'subject' => $this->faker->sentence(3),
            'body' => $this->faker->paragraph(),
            'earn_rate' => 0,
            'read_at' => null,
        ];
    }
}
