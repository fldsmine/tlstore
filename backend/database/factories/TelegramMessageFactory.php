<?php

namespace Database\Factories;

use App\Models\TelegramIdentity;
use App\Models\TelegramMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramMessage>
 */
class TelegramMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'telegram_identity_id' => TelegramIdentity::factory(),
            'admin_id' => null,
            'direction' => TelegramMessage::DIRECTION_INBOUND,
            'body' => fake()->sentence(),
            'telegram_message_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
            'status' => TelegramMessage::STATUS_RECEIVED,
            'read_at' => null,
            'delivered_at' => null,
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => TelegramMessage::DIRECTION_INBOUND,
            'status' => TelegramMessage::STATUS_RECEIVED,
            'admin_id' => null,
        ]);
    }

    public function outbound(): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => TelegramMessage::DIRECTION_OUTBOUND,
            'status' => TelegramMessage::STATUS_SENT,
            'delivered_at' => now(),
        ]);
    }
}
