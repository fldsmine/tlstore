<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = round($this->faker->randomFloat(2, 5_000, 50_000) / 1000, 2);
        $fee = round($amount * 0.1, 2);
        $net = round($amount - $fee, 2);
        $bank = $this->faker->randomElement(['Access Bank', 'GTBank', 'Zenith Bank', 'First Bank of Nigeria']);
        $rate = 1000.0; // demo withdrawals are for Nigerian users (NGN at the anchored rate)

        return [
            'user_id' => User::factory(),
            'reference' => 'WD-'.strtoupper(Str::random(10)),
            'wallet' => $this->faker->randomElement(array_keys(User::WALLET_COLUMNS)),
            'amount' => $amount,
            'fee' => $fee,
            'amount_paid' => $net,
            'local_currency' => 'NGN',
            'local_amount' => round($amount * $rate, 2),
            'local_amount_paid' => round($net * $rate, 2),
            'status' => $this->faker->randomElement(WithdrawalStatus::cases()),
            // Bank payout snapshot + currency snapshot live in meta (no dedicated columns).
            'meta' => [
                'bank_name' => $bank,
                'bank_code' => (string) $this->faker->numerify('###'),
                'bank_tag' => null,
                'account_number' => (string) $this->faker->numerify('##########'),
                'account_name' => strtoupper($this->faker->name()),
                'exchange_rate' => $rate,
                'local_currency' => 'NGN',
                'local_symbol' => '₦',
                'deduct_at' => 'request',
            ],
            'created_at' => $this->faker->dateTimeBetween('-29 days', 'now'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => WithdrawalStatus::PENDING]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => WithdrawalStatus::COMPLETED, 'processed_at' => now()]);
    }
}
