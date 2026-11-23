<?php

namespace Database\Factories;

use App\Models\Ad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ad>
 */
class AdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $impressions = $this->faker->numberBetween(2_000, 60_000);

        return [
            'name' => $this->faker->randomElement([
                'ShopNow Nigeria', 'QuickPay Solution', 'FreshMart Deals', 'RideGo Express',
                'EduLearn Academy', 'HealthPlus Clinic', 'StyleHub Fashion', 'TravelEase Tours',
            ]),
            'advertiser' => $this->faker->randomElement(['VoiceTech Inc', 'Naija Media', 'AdReach NG', 'BrightAds']),
            'description' => $this->faker->randomElement([
                'Limited-time offer — tap through before it ends.',
                'Trusted by thousands across Nigeria. See why.',
                'Fresh deals every week, delivered to your door.',
                'Sign up today and get your first reward on us.',
            ]),
            // Optional image; link is always present (it's what gets shared via Web Share).
            'image_url' => $this->faker->boolean(40) ? 'https://picsum.photos/seed/'.$this->faker->numberBetween(1, 999).'/160' : null,
            'target_url' => $this->faker->url(),
            'reward' => $reward = $this->faker->randomElement([0.005, 0.0075, 0.0125, 0.05, 0.10, 0.15, 0.20]),
            'base_reward' => $reward,
            'version' => 1,
            'impressions' => $impressions,
            'views' => (int) round($impressions * $this->faker->randomFloat(2, 0.3, 0.7)),
            'shares' => $this->faker->numberBetween(10, 400),
            'growth_percent' => $this->faker->randomElement([1.5, 2, 3, 5]),
            'growth_interval_hours' => $this->faker->randomElement([2, 3, 6]),
            'last_grown_at' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
