<?php

use App\Models\TelegramIdentity;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;

const TG_CMD_SECRET = 'test-telegram-webhook-secret';

function tgEnableWebhook(): void
{
    config()->set('services.telegram.webhook_secret', TG_CMD_SECRET);
    config()->set('services.telegram.bot_token', 'test-bot-token');
    config()->set('services.telegram.webapp_url', 'https://app.example.test/mini-app');
}

function tgCommandHeaders(): array
{
    return ['X-Telegram-Bot-Api-Secret-Token' => TG_CMD_SECRET];
}

function tgCommandUpdate(string $text, int $chatId = 500, int $fromId = 900, string $firstName = 'Tg'): array
{
    return [
        'update_id' => random_int(1, PHP_INT_MAX),
        'message' => [
            'message_id' => random_int(1, PHP_INT_MAX),
            'date' => time(),
            'chat' => ['id' => $chatId, 'type' => 'private'],
            'from' => ['id' => $fromId, 'first_name' => $firstName],
            'text' => $text,
        ],
    ];
}

/*
|--------------------------------------------------------------------------
| Webhook security
|--------------------------------------------------------------------------
*/

test('webhook rejects requests without the secret token', function () {
    tgEnableWebhook();
    Http::fake();

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/help'))->assertForbidden();

    Http::assertNothingSent();
});

test('webhook stays disabled until the secret is configured', function () {
    Http::fake();

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/help'), [
        'X-Telegram-Bot-Api-Secret-Token' => 'anything',
    ])->assertForbidden();

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Commands
|--------------------------------------------------------------------------
*/

test('help lists the available commands', function () {
    tgEnableWebhook();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/help'), tgCommandHeaders())
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains($request['text'], '/balance')
        && str_contains($request['text'], '/support'));
});

test('commands with a bot mention are normalised', function () {
    tgEnableWebhook();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/help@NextelConnectBot'), tgCommandHeaders())
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains($request['text'], '/balance'));
});

test('start and app replies carry the mini-app button', function () {
    tgEnableWebhook();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/start'), tgCommandHeaders())->assertOk();
    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/app'), tgCommandHeaders())->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains($request['text'], 'Welcome')
        && data_get($request, 'reply_markup.inline_keyboard.0.0.web_app.url') === 'https://app.example.test/mini-app');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains((string) ($request['text'] ?? ''), 'Opening Nextel'));
});

test('balance shows wallet balances for a linked account', function () {
    tgEnableWebhook();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $user = User::factory()->create();
    TelegramIdentity::create([
        'user_id' => $user->id,
        'telegram_user_id' => 900,
        'username' => 'tguser',
    ]);

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/balance', fromId: 900), tgCommandHeaders())
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains($request['text'], 'Current Balance')
        && str_contains($request['text'], '@'.$user->username));
});

test('balance prompts unlinked accounts to open the app', function () {
    tgEnableWebhook();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/balance', fromId: 31337), tgCommandHeaders())
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains($request['text'], 'not linked')
        && data_get($request, 'reply_markup.inline_keyboard.0.0.web_app.url') === 'https://app.example.test/mini-app');
});

test('support shares the contact channels', function () {
    tgEnableWebhook();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('/support'), tgCommandHeaders())
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains($request['text'], 'Email:')
        && str_contains($request['text'], 'Telegram:'));
});

test('unknown messages point to help', function () {
    tgEnableWebhook();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $this->postJson(route('webhooks.telegram'), tgCommandUpdate('hello there'), tgCommandHeaders())
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains($request['text'], '/help'));
});

test('free text from a linked Mini App user is stored for the backoffice', function () {
    tgEnableWebhook();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $user = User::factory()->create();
    $identity = TelegramIdentity::create([
        'user_id' => $user->id,
        'telegram_user_id' => 900,
        'username' => 'tguser',
    ]);

    $update = tgCommandUpdate('I cannot withdraw my ads revenue', chatId: 900, fromId: 900);
    $update['message']['message_id'] = 501;

    $this->postJson(route('webhooks.telegram'), $update, tgCommandHeaders())->assertOk();

    $this->assertDatabaseHas('telegram_messages', [
        'telegram_identity_id' => $identity->id,
        'direction' => 'inbound',
        'body' => 'I cannot withdraw my ads revenue',
        'telegram_message_id' => 501,
        'status' => 'received',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && str_contains($request['text'], 'support will reply'));

    $this->postJson(route('webhooks.telegram'), $update, tgCommandHeaders())->assertOk();

    expect(TelegramMessage::query()->where('telegram_identity_id', $identity->id)->count())->toBe(1);
});
