<?php

use App\Enums\WithdrawalStatus;
use App\Models\BankAccount;
use App\Models\Withdrawal;

function withdrawableUser(float $balance = 20.0): \App\Models\User
{
    $user = activatedUser(['country' => 'NG', 'shareholder_balance' => $balance, 'transaction_pin' => '1234']);
    BankAccount::factory()->create(['user_id' => $user->id]);

    return $user;
}

test('users can request a withdrawal with a valid pin', function () {
    $user = withdrawableUser(50.0);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'shareholder', 'amount' => 20, 'pin' => '1234'])
        ->assertCreated()
        ->assertJsonPath('data.withdrawal.wallet', 'shareholder');

    $withdrawal = Withdrawal::first();

    expect((float) $user->refresh()->shareholder_balance)->toBe(30.0)
        ->and($withdrawal->status)->toBe(WithdrawalStatus::PROCESSING)
        ->and($response->json('data.withdrawal.reference'))->toBe($withdrawal->reference);
});

test('withdrawals list is scoped to the requester', function () {
    $user = withdrawableUser();
    Withdrawal::factory()->create(['user_id' => $user->id]);
    Withdrawal::factory()->create(); // someone else's

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/withdrawals')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1);
});

test('an incorrect pin is rejected', function () {
    $user = withdrawableUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'shareholder', 'amount' => 10, 'pin' => '0000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pin']);

    expect(Withdrawal::count())->toBe(0);
});

test('amounts above the balance are rejected', function () {
    $user = withdrawableUser(10.0);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'shareholder', 'amount' => 500, 'pin' => '1234'])
        ->assertStatus(422);

    expect((float) $user->refresh()->shareholder_balance)->toBe(10.0);
});

test('amounts below the wallet minimum are rejected', function () {
    $user = withdrawableUser(50.0);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'shareholder', 'amount' => 1, 'pin' => '1234'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('a payout account is required', function () {
    $user = activatedUser(['country' => 'NG', 'shareholder_balance' => 50, 'transaction_pin' => '1234']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'shareholder', 'amount' => 10, 'pin' => '1234'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('admin-disabled withdrawals are blocked', function () {
    $user = withdrawableUser();
    $user->forceFill(['withdrawals_disabled' => true])->save();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'shareholder', 'amount' => 10, 'pin' => '1234'])
        ->assertStatus(422);
});

test('a second concurrent withdrawal from the same wallet is blocked', function () {
    $user = withdrawableUser(100.0);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'shareholder', 'amount' => 10, 'pin' => '1234'])
        ->assertCreated();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'shareholder', 'amount' => 10, 'pin' => '1234'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('unknown wallets are rejected', function () {
    $user = withdrawableUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/withdrawals', ['wallet' => 'nonexistent', 'amount' => 10, 'pin' => '1234'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['wallet']);
});
