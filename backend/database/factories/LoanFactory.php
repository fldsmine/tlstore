<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 5, 50);

        return [
            'user_id' => User::factory(),
            'amount' => $amount,
            'total_repayment' => $amount,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => LoanStatus::PROCESSING,
        ];
    }
}
