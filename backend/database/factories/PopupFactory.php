<?php

namespace Database\Factories;

use App\Models\Popup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Popup>
 */
class PopupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'heading' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(),
            'image_caption' => null,
            'header_image' => null,
            'background_image' => null,
            'primary_label' => null,
            'primary_url' => null,
            'secondary_label' => null,
            'secondary_url' => null,
            'footer_text' => null,
            'is_active' => true,
            'is_recursive' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /** Shown once, then never again. */
    public function once(): static
    {
        return $this->state(fn (): array => ['is_recursive' => false]);
    }

    /** An action link, both label and URL — a link with no label is never rendered. */
    public function withPrimaryAction(string $label = 'Learn more', string $url = 'https://nextel.example/promo'): static
    {
        return $this->state(fn (): array => ['primary_label' => $label, 'primary_url' => $url]);
    }
}
