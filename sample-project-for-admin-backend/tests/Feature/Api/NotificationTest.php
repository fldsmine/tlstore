<?php

use App\Models\CloudInboxMessage;

test('notifications are the user\'s cloud inbox, paginated', function () {
    $user = apiUser();
    CloudInboxMessage::factory()->count(25)->create(['user_id' => $user->id]);
    CloudInboxMessage::factory()->create(); // someone else's

    $page = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications?per_page=10')
        ->assertOk();

    expect($page->json('data.pagination.total'))->toBe(25)
        ->and(count($page->json('data.items')))->toBe(10);
});

test('unread count tracks unread messages only', function () {
    $user = apiUser();
    CloudInboxMessage::factory()->count(2)->create(['user_id' => $user->id]);
    CloudInboxMessage::factory()->create(['user_id' => $user->id, 'read_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 2);
});

test('a notification can be marked read', function () {
    $user = apiUser();
    $message = CloudInboxMessage::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/notifications/'.$message->id.'/read')
        ->assertOk();

    expect($message->refresh()->read_at)->not->toBeNull();
});

test('marking someone else\'s notification fails', function () {
    $user = apiUser();
    $stranger = CloudInboxMessage::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/notifications/'.$stranger->id.'/read')
        ->assertStatus(404);
});

test('read all clears every unread message', function () {
    $user = apiUser();
    CloudInboxMessage::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.updated', 3);

    expect($user->cloudInbox()->unread()->count())->toBe(0);
});

test('notification endpoints require authentication', function () {
    $this->getJson('/api/v1/notifications')->assertStatus(401);
});
