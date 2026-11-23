<?php

use App\Models\Bank;
use App\Models\BankAccount;

test('users see banks for their own country', function () {
    $user = apiUser(['country' => 'NG']);
    Bank::factory()->create(['country' => 'NG']);
    Bank::factory()->create(['country' => 'GH']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/banks')
        ->assertOk()
        ->assertJsonCount(1, 'data.banks');
});

test('nigerian users resolve their account name and save it verified', function () {
    $user = apiUser(['country' => 'NG', 'first_name' => 'Chidi', 'last_name' => 'Okafor']);
    $bank = Bank::factory()->create(['country' => 'NG']);

    $verify = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bank-account/verify', ['bank_id' => $bank->id, 'account_number' => '0123456789'])
        ->assertOk();

    expect($verify->json('data.account_name'))->toBe('CHIDI OKAFOR');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bank-account', ['bank_id' => $bank->id, 'account_number' => '0123456789'])
        ->assertCreated()
        ->assertJsonPath('data.bank_account.account_name', 'CHIDI OKAFOR')
        ->assertJsonPath('data.bank_account.status', 'verified');
});

test('non-vendors cannot replace an existing payout account', function () {
    $user = apiUser(['country' => 'NG']);
    $bank = Bank::factory()->create(['country' => 'NG']);
    BankAccount::factory()->create(['user_id' => $user->id, 'bank_id' => $bank->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bank-account', ['bank_id' => $bank->id, 'account_number' => '9999999999'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_number']);

    expect($user->bankAccounts()->count())->toBe(1);
});

test('vendors may replace their payout account', function () {
    $user = apiUser(['country' => 'NG', 'is_agent' => true]);
    $bank = Bank::factory()->create(['country' => 'NG']);
    BankAccount::factory()->create(['user_id' => $user->id, 'bank_id' => $bank->id]);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/bank-account', ['bank_id' => $bank->id, 'account_number' => '9999999999'])
        ->assertOk();

    expect($user->bankAccounts()->count())->toBe(1)
        ->and($user->bankAccounts()->first()->account_number)->toBe('9999999999');
});

test('bank accounts must belong to the user\'s country', function () {
    $user = apiUser(['country' => 'NG']);
    $foreignBank = Bank::factory()->create(['country' => 'GH']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bank-account', ['bank_id' => $foreignBank->id, 'account_number' => '0123456789'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_id']);
});

test('foreign users save self-confirmed mobile money wallets', function () {
    $user = apiUser(['country' => 'GH']);
    $bank = Bank::factory()->create(['country' => 'GH', 'type' => 'mobile_money']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bank-account', [
            'bank_id' => $bank->id,
            'account_number' => '+233 201234567',
            'account_name' => 'Kwame Mensah',
            'confirmed' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.bank_account.status', 'pending');
});

test('viewing the payout account returns null when unset', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/bank-account')
        ->assertOk()
        ->assertJsonPath('data.bank_account', null);
});
