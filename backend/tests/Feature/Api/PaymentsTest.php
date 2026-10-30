<?php

test('bills wallet top-up is unavailable without a configured gateway', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bills/topup', ['amount' => 1000])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Card top-up is not available right now.');
});

test('top-up amounts are validated server-side', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bills/topup', ['amount' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('finalizing an unknown payment reference never credits', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/payments/NOPE-123/finalize')
        ->assertOk()
        ->assertJsonPath('data.status', 'failed');

    expect((float) $user->refresh()->bills_balance)->toBe(0.0);
});

test('payment endpoints require authentication', function () {
    $this->postJson('/api/v1/bills/topup', ['amount' => 1000])->assertStatus(401);
    $this->postJson('/api/v1/payments/REF-1/finalize')->assertStatus(401);
});
