<?php

namespace Database\Factories;

use App\Enums\RewardTargetStatus;
use App\Models\RewardTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardTarget>
 */
class RewardTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-20 days', 'now');

        return [
            'user_id' => User::factory(),
            'date' => $date,
            'target_amount' => $this->faker->randomElement([50, 100, 200]),
            'created_at' => (clone $date)->setTime(8, 0),
            'reward_amount' => 0,
            'reward_percent' => 0,
            'status' => RewardTargetStatus::ACTIVE,
            'achieved_at' => null,
            'claimed_at' => null,
        ];
    }

    /**
     * Derive the reward from the FINAL target_amount (after any create() override) for
     * achieved/claimed rows, so the reward always matches the target it was earned on.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (RewardTarget $target): void {
            $hit = in_array($target->status, [RewardTargetStatus::ACHIEVED, RewardTargetStatus::CLAIMED], true);

            if ($hit && (float) $target->reward_amount <= 0) {
                $percent = (float) config('settings.rewards.target_percentage', 4);
                $target->reward_percent = $percent;
                $target->reward_amount = round((float) $target->target_amount * $percent / 100, 2);
            }
        });
    }

    /** Target hit — reward unlocked, waiting to be claimed. */
    public function achieved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RewardTargetStatus::ACHIEVED,
            'achieved_at' => now(),
        ]);
    }

    /** Reward already collected. */
    public function claimed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RewardTargetStatus::CLAIMED,
            'achieved_at' => now(),
            'claimed_at' => now(),
        ]);
    }

    /** The day ended without hitting the target. */
    public function missed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RewardTargetStatus::MISSED,
        ]);
    }
}
