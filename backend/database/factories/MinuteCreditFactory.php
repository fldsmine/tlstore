<?php

namespace Database\Factories;

use App\Enums\PayLaterStatus;
use App\Models\MinuteCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MinuteCredit>
 */
class MinuteCreditFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_name' => 'Test Plan',
            'amount' => 5,
            'minutes' => 15,
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => PayLaterStatus::ACTIVE,
        ];
    }
}
