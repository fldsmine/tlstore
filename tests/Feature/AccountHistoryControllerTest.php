<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_history_filters_and_returns_currency_grouped_stats(): void
    {
        $user = User::query()->create([
            'full_name' => 'History User',
            'username' => 'history-user',
            'email' => 'history-user@example.test',
            'country_code' => null,
            'preferred_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);

        $this->transaction($user, TransactionType::APP_REVIEW, 'credit', 21, 'review', now());
        $this->transaction($user, TransactionType::ADS_SHARE, 'credit', 2, 'share', now()->subDay());
        $this->transaction($user, TransactionType::WITHDRAWAL, 'debit', 3, 'topup_fund', now());

        $token = $user->createToken('account-history-test')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/me/account-history?per_page=5&category=review&status=posted&from_date='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.type', 'app_review')
            ->assertJsonPath('data.data.0.amount_minor', 2100)
            ->assertJsonPath('meta.stats.total', 3)
            ->assertJsonPath('meta.stats.earned_minor_by_currency.NGN', 2300)
            ->assertJsonPath('meta.stats.spent_minor_by_currency.NGN', 300);
    }

    private function transaction(User $user, TransactionType $type, string $direction, float $amount, string $wallet, $createdAt): void
    {
        Transaction::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'fee' => 0,
            'wallet' => $wallet,
            'status' => 'completed',
            'reference' => (string) Str::uuid(),
            'description' => 'Test transaction',
            'meta' => ['currency' => 'NGN', 'currency_minor_units' => 2],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
