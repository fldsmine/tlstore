<?php

use App\Models\Transaction;

test('wallets returns every balance plus totals', function () {
    $user = activatedUser(['shareholder_balance' => 12.5, 'referral_bonus_balance' => 3.25]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/wallets')
        ->assertOk()
        ->assertJsonPath('data.wallets.0.wallet', 'shareholder')
        ->assertJsonPath('data.total_balance_usd', 15.75);
});

test('transactions are paginated and filtered', function () {
    $user = activatedUser();
    Transaction::factory()->count(25)->create(['user_id' => $user->id]);
    Transaction::factory()->create(['user_id' => $user->id, 'direction' => 'credit', 'status' => 'pending']);

    $page = $this->actingAs($user, 'sanctum')->getJson('/api/v1/transactions?per_page=10')->assertOk();

    expect($page->json('data.pagination.total'))->toBe(26)
        ->and($page->json('data.pagination.last_page'))->toBe(3)
        ->and(count($page->json('data.items')))->toBe(10);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/transactions?filter=pending')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1);
});

test('transaction search matches reference or description', function () {
    $user = activatedUser();
    $tx = Transaction::factory()->create([
        'user_id' => $user->id,
        'reference' => 'FINDME-123',
        'description' => 'Special payment',
    ]);
    Transaction::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/transactions?search=FINDME')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.items.0.id', $tx->id);
});

test('users can view their own transaction only', function () {
    $user = activatedUser();
    $other = activatedUser();
    $mine = Transaction::factory()->create(['user_id' => $user->id]);
    $theirs = Transaction::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/transactions/'.$mine->id)
        ->assertOk()
        ->assertJsonPath('data.transaction.id', $mine->id);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/transactions/'.$theirs->id)
        ->assertStatus(404);
});

test('transaction list rejects invalid filters', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/transactions?filter=bogus')
        ->assertStatus(422);
});
