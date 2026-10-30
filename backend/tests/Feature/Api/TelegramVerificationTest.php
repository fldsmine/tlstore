<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Sign initData the same way the official Telegram algorithm does. */
function tgVerifyInitData(array $user = [], ?string $botToken = null): string
{
    $fields = [
        'user' => json_encode(array_merge([
            'id' => 424242,
            'first_name' => 'Tg',
            'username' => 'tgverifier',
        ], $user)),
        'auth_date' => time(),
        'query_id' => 'AAF-verify-query',
    ];

    $pairs = [];

    foreach ($fields as $key => $value) {
        $pairs[] = $key.'='.(string) $value;
    }

    sort($pairs, SORT_STRING);

    $secret = hash_hmac('sha256', $botToken ?? 'test-bot-token', 'WebAppData', true);
    $fields['hash'] = hash_hmac('sha256', implode("\n", $pairs), $secret);

    return http_build_query($fields);
}

function tgRegisterPayload(string $initData): array
{
    return [
        'full_name' => 'Tg Verifier',
        'username' => 'tgverify'.random_int(100, 999),
        'email' => 'tgverify'.random_int(1000, 9999).'@example.com',
        'phone' => '+23480'.random_int(10000000, 99999999),
        'country' => 'NG',
        'password' => 'Secret-123',
        'agree_terms' => true,
        'init_data' => $initData,
    ];
}

beforeEach(function () {
    seedNigeria();
    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.auth_max_age' => 3600,
    ]);
});

test('mini-app registration delivers the code via telegram and auto-verifies', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $initData = tgVerifyInitData();

    $response = $this->postJson('/api/v1/register', tgRegisterPayload($initData))
        ->assertCreated()
        ->assertJsonPath('data.verification_required', true)
        ->assertJsonPath('data.verification_channel', 'telegram');

    // The bot DM actually went out with the OTP code.
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.telegram.org')
            && str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 424242
            && str_contains($request['text'], 'verification code');
    });

    $email = $response->json('data.user.email');
    $user = User::query()->where('email', $email)->first();
    expect($user->email_verified_at)->toBeNull();

    // The Mini App confirms with the same Telegram session — auto-verify.
    $this->withToken((string) $response->json('data.token'))
        ->postJson('/api/v1/telegram/verify', ['init_data' => $initData])
        ->assertOk()
        ->assertJsonPath('data.verified', true);

    expect($user->refresh()->email_verified_at)->not->toBeNull();

    // Intent is one-shot: a second call reports already-verified.
    $this->withToken((string) $response->json('data.token'))
        ->postJson('/api/v1/telegram/verify', ['init_data' => $initData])
        ->assertOk();
});

test('registration falls back to email when the bot cannot reach the user', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 403)]);

    $response = $this->postJson('/api/v1/register', tgRegisterPayload(tgVerifyInitData()))
        ->assertCreated()
        ->assertJsonPath('data.verification_channel', 'email');

    // No intent stored — the auto-confirm endpoint refuses politely.
    $this->withToken((string) $response->json('data.token'))
        ->postJson('/api/v1/telegram/verify', ['init_data' => tgVerifyInitData()])
        ->assertStatus(422);
});

test('plain registration keeps the email channel', function () {
    Http::fake();

    $this->postJson('/api/v1/register', [
        'full_name' => 'Web User',
        'username' => 'webuser'.random_int(100, 999),
        'email' => 'web'.random_int(1000, 9999).'@example.com',
        'phone' => '+23480'.random_int(10000000, 99999999),
        'country' => 'NG',
        'password' => 'Secret-123',
        'agree_terms' => true,
    ])->assertCreated()
        ->assertJsonPath('data.verification_required', true)
        ->assertJsonPath('data.verification_channel', 'email');

    Http::assertNothingSent();
});

test('telegram verify rejects a session that has no pending intent for the account', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    // Register from the mini app...
    $first = $this->postJson('/api/v1/register', tgRegisterPayload(tgVerifyInitData(['id' => 111])))->assertCreated();

    // ...but a *different* account tries to auto-verify with its own session.
    $second = $this->postJson('/api/v1/register', [
        'full_name' => 'Other Person',
        'username' => 'otherperson'.random_int(100, 999),
        'email' => 'other'.random_int(1000, 9999).'@example.com',
        'phone' => '+23480'.random_int(10000000, 99999999),
        'country' => 'NG',
        'password' => 'Secret-123',
        'agree_terms' => true,
    ])->assertCreated();

    $this->withToken((string) $second->json('data.token'))
        ->postJson('/api/v1/telegram/verify', ['init_data' => tgVerifyInitData(['id' => 111])])
        ->assertStatus(422);

    expect(Cache::get('telegram-verify:111'))->not->toBeNull();
});

test('telegram verify requires authentication', function () {
    $this->postJson('/api/v1/telegram/verify', ['init_data' => tgVerifyInitData()])
        ->assertStatus(401);
});
