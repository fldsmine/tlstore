<?php

namespace Database\Factories;

use App\Enums\ChatMessageSender;
use App\Enums\ChatMessageType;
use App\Models\Chat;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_id' => Chat::factory(),
            'sender' => ChatMessageSender::CUSTOMER,
            'type' => ChatMessageType::TEXT,
            'body' => $this->faker->sentence(),
            'meta' => null,
        ];
    }

    public function system(string $body): static
    {
        return $this->state(['sender' => ChatMessageSender::SYSTEM, 'type' => ChatMessageType::SYSTEM, 'body' => $body]);
    }
}
