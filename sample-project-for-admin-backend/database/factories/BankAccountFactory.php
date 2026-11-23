<?php

namespace Database\Factories;

use App\Enums\BankAccountStatus;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_id' => Bank::factory(),
            'account_number' => $this->faker->numerify('##########'),
            'account_name' => $this->faker->name(),
            'status' => BankAccountStatus::VERIFIED,
        ];
    }
}
