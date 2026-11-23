<?php

use App\Livewire\Backoffice\Telegram\ListTelegramUsers;
use App\Livewire\Backoffice\Telegram\ShowTelegramUser;
use App\Models\Admin;
use App\Models\TelegramIdentity;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function telegramAdmin(): Admin
{
    return Admin::factory()->regular()->create();
}

function linkedMiniAppUser(array $identity = []): TelegramIdentity
{
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
        'username' => 'adaokafor',
    ]);

    return TelegramIdentity::factory()->create(array_merge([
        'user_id' => $user->id,
        'telegram_user_id' => 424242,
        'username' => 'ada_tg',
        'first_name' => 'Ada',
        'last_name' => 'TG',
    ], $identity));
}

test('guests cannot browse Mini App users', function () {
    $this->get(route('backoffice.telegram-users.index'))
        ->assertRedirect(route('backoffice.login'));
});

test('admins see the Mini App user total and can open a conversation', function () {
    $admin = telegramAdmin();
    $identity = linkedMiniAppUser();
    TelegramIdentity::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('backoffice.telegram-users.index'))
        ->assertOk()
        ->assertSee('Mini App users')
        ->assertSee('ada_tg')
        ->assertSee('@adaokafor');

    Livewire::test(ListTelegramUsers::class)
        ->assertViewHas('totalUsers', 2)
        ->assertSee('Ada TG');

    $this->get(route('backoffice.telegram-users.show', $identity))
        ->assertOk()
        ->assertSee('Message this user')
        ->assertSee('@adaokafor');
});

test('admins can search Mini App users by Telegram handle or Nextel username', function () {
    $this->actingAs(telegramAdmin(), 'admin');
    linkedMiniAppUser();
    TelegramIdentity::factory()->create([
        'username' => 'other_handle',
        'first_name' => 'Other',
    ]);

    Livewire::test(ListTelegramUsers::class)
        ->set('search', 'ada_tg')
        ->assertSee('Ada TG')
        ->assertDontSee('Other');

    Livewire::test(ListTelegramUsers::class)
        ->set('search', 'adaokafor')
        ->assertSee('Ada TG')
        ->assertDontSee('Other');
});

test('an admin can DM one Mini App user over Telegram', function () {
    config()->set('services.telegram.bot_token', 'test-bot-token');
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);

    $admin = telegramAdmin();
    $identity = linkedMiniAppUser();
    $this->actingAs($admin, 'admin');

    Livewire::test(ShowTelegramUser::class, ['identity' => $identity])
        ->set('reply', 'Your package upgrade is confirmed.')
        ->call('sendReply')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('telegram_messages', [
        'telegram_identity_id' => $identity->id,
        'admin_id' => $admin->id,
        'direction' => TelegramMessage::DIRECTION_OUTBOUND,
        'status' => TelegramMessage::STATUS_SENT,
        'body' => 'Your package upgrade is confirmed.',
        'telegram_message_id' => 77,
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && (int) $request['chat_id'] === 424242
        && $request['text'] === 'Your package upgrade is confirmed.');
});

test('a failed Telegram DM is kept on the thread as not delivered', function () {
    config()->set('services.telegram.bot_token', 'test-bot-token');
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);

    $identity = linkedMiniAppUser();
    $this->actingAs(telegramAdmin(), 'admin');

    Livewire::test(ShowTelegramUser::class, ['identity' => $identity])
        ->set('reply', 'Can you see this?')
        ->call('sendReply')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('telegram_messages', [
        'telegram_identity_id' => $identity->id,
        'direction' => TelegramMessage::DIRECTION_OUTBOUND,
        'status' => TelegramMessage::STATUS_FAILED,
        'body' => 'Can you see this?',
    ]);
});

test('support admins can reply but cannot broadcast to everyone', function () {
    config()->set('services.telegram.bot_token', 'test-bot-token');
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 8]])]);

    $support = Admin::factory()->support()->create();
    $identity = linkedMiniAppUser();
    $this->actingAs($support, 'admin');

    Livewire::test(ShowTelegramUser::class, ['identity' => $identity])
        ->set('reply', 'We are looking into this.')
        ->call('sendReply')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('telegram_messages', [
        'telegram_identity_id' => $identity->id,
        'admin_id' => $support->id,
        'body' => 'We are looking into this.',
    ]);

    Livewire::test(ListTelegramUsers::class)
        ->call('openBroadcast')
        ->assertForbidden();
});

test('a regular admin can broadcast a DM to every Mini App user', function () {
    config()->set('services.telegram.bot_token', 'test-bot-token');
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]])]);

    $first = linkedMiniAppUser();
    $second = TelegramIdentity::factory()->create(['telegram_user_id' => 999001]);
    $this->actingAs(telegramAdmin(), 'admin');

    Livewire::test(ListTelegramUsers::class)
        ->call('openBroadcast')
        ->set('broadcastBody', 'Mini App maintenance tonight at 9pm WAT.')
        ->call('sendBroadcast')
        ->assertHasNoErrors();

    expect(TelegramMessage::query()->where('direction', TelegramMessage::DIRECTION_OUTBOUND)->count())->toBe(2);

    $this->assertDatabaseHas('telegram_messages', [
        'telegram_identity_id' => $first->id,
        'body' => 'Mini App maintenance tonight at 9pm WAT.',
        'status' => TelegramMessage::STATUS_SENT,
    ]);
    $this->assertDatabaseHas('telegram_messages', [
        'telegram_identity_id' => $second->id,
        'body' => 'Mini App maintenance tonight at 9pm WAT.',
        'status' => TelegramMessage::STATUS_SENT,
    ]);

    Http::assertSentCount(2);
});

test('opening a conversation marks inbound messages as read', function () {
    $identity = linkedMiniAppUser();
    $message = TelegramMessage::factory()->inbound()->create([
        'telegram_identity_id' => $identity->id,
        'body' => 'I cannot withdraw',
        'read_at' => null,
    ]);

    $this->actingAs(telegramAdmin(), 'admin');

    Livewire::test(ShowTelegramUser::class, ['identity' => $identity])
        ->assertSee('I cannot withdraw');

    expect($message->fresh()->read_at)->not->toBeNull();
});
