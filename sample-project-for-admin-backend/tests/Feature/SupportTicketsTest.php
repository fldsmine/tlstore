<?php

use App\Enums\AdminRole;
use App\Livewire\Backoffice\Support\ShowSupportTicket;
use App\Livewire\ContactUs;
use App\Livewire\Dashboard\Account\SupportTickets;
use App\Mail\ContactMessage;
use App\Mail\SupportTicketNotice;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('an authenticated user can open a private ticket and only sees their own tickets', function () {
    Mail::fake();
    $user = activatedUser(['transaction_pin' => '1234']);
    $this->actingAs($user);

    Livewire::test(SupportTickets::class)
        ->set('subject', 'Cannot update my profile')
        ->set('category', 'account')
        ->set('newMessage', 'I need help changing the email address on my account.')
        ->call('createTicket')
        ->assertHasNoErrors();

    $ticket = $user->supportTickets()->with('messages')->sole();
    expect($ticket->status)->toBe(SupportTicket::STATUS_OPEN)
        ->and($ticket->messages)->toHaveCount(1)
        ->and($ticket->messages->first()->sender_type)->toBe(SupportTicketMessage::SENDER_USER);

    $otherUser = activatedUser(['transaction_pin' => '1234']);
    $this->actingAs($otherUser);

    Livewire::test(SupportTickets::class)
        ->assertDontSee('Cannot update my profile')
        ->set('selectedTicketId', $ticket->id)
        ->assertDontSee('I need help changing the email address on my account.');
});

test('authenticated users can reach support before package onboarding is complete', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('support.tickets'))
        ->assertOk();
});

test('public contact remains email-only and guests cannot access the ticket API', function () {
    Mail::fake();

    Livewire::test(ContactUs::class)
        ->set('name', 'Guest Customer')
        ->set('email', 'guest@example.test')
        ->set('message', 'I have a question about Nextel services.')
        ->call('submit')
        ->assertHasNoErrors();

    Mail::assertQueued(ContactMessage::class);
    expect(SupportTicket::query()->count())->toBe(0);

    Auth::forgetGuards();
    $this->postJson('/api/v1/support-tickets', [
        'subject' => 'Please help',
        'category' => 'general',
        'message' => 'I need help with my account.',
    ])->assertUnauthorized();

    $this->get(route('support.tickets'))->assertRedirect();
    $this->get('/contact/ticket/00000000-0000-7000-8000-000000000000')->assertNotFound();
    $this->get('/contact/ticket-access/00000000-0000-7000-8000-000000000000/'.str_repeat('a', 64))
        ->assertNotFound();
});

test('registered users open private API tickets with server-derived identity and ownership', function () {
    Mail::fake();

    $owner = apiUser(['first_name' => 'Ada', 'last_name' => 'Okafor']);
    $otherUser = apiUser();
    $ownerToken = $owner->createToken('android')->plainTextToken;
    $otherToken = $otherUser->createToken('android')->plainTextToken;

    Auth::forgetGuards();
    $response = $this->withToken($ownerToken)->postJson('/api/v1/support-tickets', [
        'subject' => 'Cannot update my profile',
        'category' => 'account',
        'message' => 'I need help changing the email address on my account.',
        // Client identity must never determine the ticket requester.
        'user_id' => $otherUser->id,
        'contact_name' => 'Impersonated Name',
        'contact_email' => 'impersonated@example.test',
    ])->assertCreated()
        ->assertJsonPath('data.user.name', 'Ada Okafor')
        ->assertJsonPath('data.user.email', $owner->email)
        ->assertJsonPath('data.ticket.subject', 'Cannot update my profile');

    $ticketId = $response->json('data.ticket.id');
    $ticket = SupportTicket::query()->with('messages')->findOrFail($ticketId);

    expect($ticket->user_id)->toBe($owner->id)
        ->and($ticket->contact_name)->toBe('Ada Okafor')
        ->and($ticket->contact_email)->toBe($owner->email)
        ->and($ticket->messages)->toHaveCount(1)
        ->and($ticket->messages->first()->sender_type)->toBe(SupportTicketMessage::SENDER_USER);

    Auth::forgetGuards();
    $this->withToken($otherToken)
        ->getJson('/api/v1/support-tickets/'.$ticketId)
        ->assertNotFound();

    $this->withToken($otherToken)
        ->postJson('/api/v1/support-tickets/'.$ticketId.'/messages', ['message' => 'Impersonated reply'])
        ->assertNotFound();

    Auth::forgetGuards();
    $this->withToken($ownerToken)
        ->getJson('/api/v1/support-tickets/'.$ticketId)
        ->assertOk()
        ->assertJsonPath('data.messages.0.body', 'I need help changing the email address on my account.');

    $this->withToken($ownerToken)
        ->postJson('/api/v1/support-tickets/'.$ticketId.'/messages', ['message' => 'Here are more details.'])
        ->assertOk();

    expect($ticket->fresh()->messages()->count())->toBe(2);
});

test('support admins can answer tickets without general backoffice write access', function () {
    Mail::fake();

    $supportAdmin = Admin::factory()->support()->create();
    expect($supportAdmin->role)->toBe(AdminRole::SUPPORT)
        ->and($supportAdmin->canWrite())->toBeFalse();

    $user = activatedUser(['transaction_pin' => '1234']);
    $ticket = SupportTicket::query()->create([
        'user_id' => $user->id,
        'contact_name' => $user->full_name,
        'contact_email' => $user->email,
        'subject' => 'Payment status question',
        'category' => 'payments',
        'status' => SupportTicket::STATUS_OPEN,
        'last_message_at' => now(),
    ]);
    $ticket->messages()->create([
        'user_id' => $user->id,
        'sender_type' => SupportTicketMessage::SENDER_USER,
        'sender_name' => $user->full_name,
        'body' => 'Could you check this payment for me?',
    ]);

    $this->actingAs($supportAdmin, 'admin')
        ->get(route('backoffice.support-tickets.show', $ticket->id))
        ->assertOk()
        ->assertSee('Payment status question');

    Livewire::test(ShowSupportTicket::class, ['ticket' => $ticket])
        ->set('reply', 'We have checked the payment and updated its status.')
        ->call('sendReply')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_WAITING_ON_USER);
    $this->assertDatabaseHas('support_ticket_messages', [
        'support_ticket_id' => $ticket->id,
        'admin_id' => $supportAdmin->id,
        'sender_type' => SupportTicketMessage::SENDER_ADMIN,
        'body' => 'We have checked the payment and updated its status.',
    ]);
    Mail::assertQueued(SupportTicketNotice::class);

    $this->get(route('backoffice.settings.general'))->assertForbidden();
});

test('the profile setting shortcut is emitted only for mini-app sessions with a native bridge check', function () {
    $user = activatedUser(['transaction_pin' => '1234']);
    $this->actingAs($user);

    $this->get(route('profile'))->assertOk()->assertDontSee('openAppSettings');

    $this->withSession(['mini_app' => true])
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('Setting')
        ->assertSee('window.Android')
        ->assertSee('openAppSettings');
});
