<?php

use App\Models\Chat;
use App\Models\ChatMessage;

test('opening a conversation creates one thread per pair', function () {
    $customer = activatedUser();
    $agent = activatedUser(['is_agent' => true]);

    $first = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/chats', ['agent_username' => $agent->username])
        ->assertOk();

    $second = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/chats', ['agent_username' => $agent->username])
        ->assertOk();

    expect($first->json('data.chat.id'))->toBe($second->json('data.chat.id'))
        ->and(Chat::count())->toBe(1);
});

test('regular users cannot open conversations', function () {
    $customer = activatedUser();
    $nonAgent = activatedUser();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/chats', ['agent_username' => $nonAgent->username])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['agent']);
});

test('messages flow both ways and appear in the thread', function () {
    $customer = activatedUser();
    $agent = activatedUser(['is_agent' => true]);
    $chat = Chat::factory()->create(['customer_id' => $customer->id, 'agent_id' => $agent->id]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/chats/'.$chat->id.'/messages', ['body' => 'Hello vendor!'])
        ->assertCreated();

    $this->actingAs($agent, 'sanctum')
        ->postJson('/api/v1/chats/'.$chat->id.'/messages', ['body' => 'Hi, how can I help?'])
        ->assertCreated();

    $response = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/chats/'.$chat->id.'/messages')
        ->assertOk();

    expect($response->json('data.pagination.total'))->toBe(2)
        ->and($response->json('data.items.0.body'))->toBe('Hello vendor!')
        ->and($response->json('data.items.1.body'))->toBe('Hi, how can I help?');
});

test('strangers cannot read or post in a conversation', function () {
    $customer = activatedUser();
    $agent = activatedUser(['is_agent' => true]);
    $stranger = activatedUser();
    $chat = Chat::factory()->create(['customer_id' => $customer->id, 'agent_id' => $agent->id]);

    $this->actingAs($stranger, 'sanctum')
        ->getJson('/api/v1/chats/'.$chat->id.'/messages')
        ->assertStatus(403);

    $this->actingAs($stranger, 'sanctum')
        ->postJson('/api/v1/chats/'.$chat->id.'/messages', ['body' => 'sneaky'])
        ->assertStatus(403);
});

test('the chat list shows the other participant and unread counts', function () {
    $customer = activatedUser();
    $agent = activatedUser(['is_agent' => true]);
    $chat = Chat::factory()->create(['customer_id' => $customer->id, 'agent_id' => $agent->id]);
    ChatMessage::factory()->count(2)->create(['chat_id' => $chat->id, 'sender' => 'agent']);

    $response = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/chats')
        ->assertOk();

    expect($response->json('data.chats.0.other.id'))->toBe($agent->id)
        ->and($response->json('data.chats.0.unread_count'))->toBe(2);

    // The agent side has zero unread (they wrote the messages).
    $agentView = $this->actingAs($agent, 'sanctum')
        ->getJson('/api/v1/chats')
        ->assertOk();

    expect($agentView->json('data.chats.0.unread_count'))->toBe(0);
});

test('marking a conversation read resets the unread count', function () {
    $customer = activatedUser();
    $agent = activatedUser(['is_agent' => true]);
    $chat = Chat::factory()->create(['customer_id' => $customer->id, 'agent_id' => $agent->id]);
    ChatMessage::factory()->create(['chat_id' => $chat->id, 'sender' => 'agent']);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/chats/'.$chat->id.'/read')
        ->assertOk();

    $response = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/chats')
        ->assertOk();

    expect($response->json('data.chats.0.unread_count'))->toBe(0);
});
