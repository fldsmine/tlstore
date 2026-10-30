<?php

use App\Models\VtuOrder;

test('categories list the five bill services', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vas/categories')
        ->assertOk()
        ->assertJsonCount(5, 'data.categories');
});

test('a category exposes its billers and wallets', function () {
    $user = activatedUser(['bills_balance' => 10]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vas/categories/airtime')
        ->assertOk()
        ->assertJsonPath('data.category.key', 'airtime');

    expect(count($response->json('data.providers')))->toBeGreaterThan(0);
});

test('unknown categories 404', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vas/categories/crypto')
        ->assertStatus(404);
});

test('variations require a valid category', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vas/variations/mtn-data')
        ->assertStatus(422);
});

test('purchase fails fast when no bill provider is configured', function () {
    $user = activatedUser(['country' => 'NG', 'bills_balance' => 100, 'transaction_pin' => '1234']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/vas/purchase', [
            'category' => 'airtime',
            'service_id' => 'mtn',
            'amount' => 100,
            'recipient' => '08012345678',
            'pin' => '1234',
        ])
        ->assertStatus(422);

    expect(VtuOrder::count())->toBe(0);
});

test('purchase validates the payload', function () {
    $user = activatedUser(['transaction_pin' => '1234']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/vas/purchase', ['category' => 'bogus'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category', 'service_id', 'recipient', 'pin']);
});

test('orders list is scoped to the requester and filterable', function () {
    $user = activatedUser();
    VtuOrder::factory()->count(2)->create(['user_id' => $user->id, 'service' => 'airtime']);
    VtuOrder::factory()->create(['user_id' => $user->id, 'service' => 'data']);
    VtuOrder::factory()->create(); // someone else's

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vas/orders')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 3);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vas/orders?service=airtime')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);
});

test('order detail is reachable by id or request id, never across users', function () {
    $user = activatedUser();
    $other = activatedUser();
    $order = VtuOrder::factory()->create(['user_id' => $user->id]);
    $stranger = VtuOrder::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vas/orders/'.$order->request_id)
        ->assertOk()
        ->assertJsonPath('data.order.id', $order->id);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/vas/orders/'.$stranger->id)
        ->assertStatus(404);
});
