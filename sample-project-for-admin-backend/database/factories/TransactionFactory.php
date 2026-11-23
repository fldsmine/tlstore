<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(TransactionType::cases());

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'direction' => $type->isCredit() ? 'credit' : 'debit',
            'amount' => $this->faker->randomFloat(2, 0.2, 50),
            'wallet' => null,
            'status' => 'completed',
            'reference' => 'TXN-'.strtoupper(Str::random(10)),
            'description' => $type->getName(),
            'meta' => null,
            'created_at' => $this->faker->dateTimeBetween('-29 days', 'now'),
        ];
    }
}
