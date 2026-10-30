<?php

namespace Database\Factories;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'type' => AnnouncementType::INFO,
            'is_active' => true,
            'is_dismissible' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
