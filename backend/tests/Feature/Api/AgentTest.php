<?php

use App\Models\Package;

function vendor(array $attributes = []): \App\Models\User
{
    return activatedUser(array_merge(['is_agent' => true, 'agent_balance' => 100], $attributes));
}

test('the agent area is closed to non-vendors', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/agent/summary')
        ->assertStatus(403);
});

test('vendors see their float balance and token stats', function () {
    $agent = vendor();
    \App\Models\Coupon::factory()->count(2)->create(['agent_id' => $agent->id]);
    \App\Models\Coupon::factory()->used()->create(['agent_id' => $agent->id]);

    $this->actingAs($agent, 'sanctum')
        ->getJson('/api/v1/agent/summary')
        ->assertOk()
        ->assertJsonPath('data.float_balance_usd', 100)
        ->assertJsonPath('data.tokens.total', 3)
        ->assertJsonPath('data.tokens.active', 2)
        ->assertJsonPath('data.tokens.used', 1);
});

test('the mintable plan catalogue includes packages', function () {
    $agent = vendor();
    Package::factory()->create();

    $response = $this->actingAs($agent, 'sanctum')
        ->getJson('/api/v1/agent/plans')
        ->assertOk();

    expect(count($response->json('data.plans')))->toBeGreaterThan(0);
    expect($response->json('data.plans.0.key'))->toStartWith('package:');
});

test('minting tokens charges the vendor float and creates a batch', function () {
    $agent = vendor(['agent_balance' => 50]);
    $package = Package::factory()->create(['agent_price' => 8]);

    $response = $this->actingAs($agent, 'sanctum')
        ->postJson('/api/v1/agent/tokens', [
            'plan_key' => 'package:'.$package->id,
            'quantity' => 3,
        ])
        ->assertCreated()
        ->assertJsonPath('data.cost_usd', 24);

    expect((float) $agent->refresh()->agent_balance)->toBe(26.0)
        ->and(count($response->json('data.tokens')))->toBe(3);
});

test('minting beyond the float balance is rejected', function () {
    $agent = vendor(['agent_balance' => 5]);
    $package = Package::factory()->create(['agent_price' => 8]);

    $this->actingAs($agent, 'sanctum')
        ->postJson('/api/v1/agent/tokens', [
            'plan_key' => 'package:'.$package->id,
            'quantity' => 3,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['quantity']);
});

test('tokens and batches list only this vendor\'s records', function () {
    $agent = vendor();
    $other = vendor();
    \App\Models\Coupon::factory()->count(2)->create(['agent_id' => $agent->id]);
    \App\Models\Coupon::factory()->create(['agent_id' => $other->id]);
    foreach ([$agent, $agent, $other] as $vendor) {
        \App\Models\CouponBatch::create([
            'agent_id' => $vendor->id,
            'type' => \App\Enums\CouponType::ACTIVATION->value,
            'region' => 'nigeria',
            'quantity' => 1,
        ]);
    }

    $this->actingAs($agent, 'sanctum')
        ->getJson('/api/v1/agent/tokens')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);

    $this->actingAs($agent, 'sanctum')
        ->getJson('/api/v1/agent/batches')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);
});

test('special vendors can transfer float to another vendor', function () {
    $sender = vendor(['is_special' => true, 'agent_balance' => 100]);
    $receiver = vendor(['agent_balance' => 0]);

    $response = $this->actingAs($sender, 'sanctum')
        ->postJson('/api/v1/agent/transfer', [
            'recipient_username' => $receiver->username,
            'amount' => 30,
        ], ['Idempotency-Key' => 'test-key-1'])
        ->assertOk()
        ->assertJsonPath('data.amount_usd', 30);

    expect((float) $sender->refresh()->agent_balance)->toBe(70.0)
        ->and((float) $receiver->refresh()->agent_balance)->toBe(30.0);

    // Same idempotency key → same transaction, no double debit.
    $this->actingAs($sender, 'sanctum')
        ->postJson('/api/v1/agent/transfer', [
            'recipient_username' => $receiver->username,
            'amount' => 30,
        ], ['Idempotency-Key' => 'test-key-1'])
        ->assertOk();

    expect((float) $sender->refresh()->agent_balance)->toBe(70.0);
});

test('regular vendors cannot transfer funds', function () {
    $sender = vendor(['is_special' => false, 'agent_balance' => 100]);
    $receiver = vendor();

    $this->actingAs($sender, 'sanctum')
        ->postJson('/api/v1/agent/transfer', [
            'recipient_username' => $receiver->username,
            'amount' => 30,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});
