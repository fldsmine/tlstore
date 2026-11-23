<?php

use App\Models\TelegramIdentity;
use App\Models\User;
use App\Services\Telegram\TelegramAuthService;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Builds a Telegram WebApp initData string signed the same way Telegram does,
 * so the backend's HMAC validation passes. Mirrors the official algorithm:
 * data_check_string (sorted `key=value`, minus hash) HMAC'd with a key derived
 * from the bot token.
 *
 * @param  array<string, mixed>  $user
 * @param  array<string, mixed>  $extra
 */
function telegramInitData(
    array $user = [],
    ?int $authDate = null,
    ?string $botToken = null,
    array $extra = [],
    bool $withHash = true,
    ?string $hashOverride = null,
): string {
    $fields = array_merge([
        'user' => json_encode(array_merge([
            'id' => 777000,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'username' => 'ada',
            'language_code' => 'en',
        ], $user)),
        'auth_date' => $authDate ?? time(),
        'query_id' => 'AAF-test-query',
    ], $extra);

    $pairs = [];

    foreach ($fields as $key => $value) {
        $pairs[] = $key.'='.(string) $value;
    }

    sort($pairs, SORT_STRING);

    $secret = hash_hmac('sha256', $botToken ?? 'test-bot-token', 'WebAppData', true);
    $hash = $hashOverride ?? hash_hmac('sha256', implode("\n", $pairs), $secret);

    if (! $withHash) {
        return http_build_query($fields);
    }

    return http_build_query($fields + ['hash' => $hash]);
}

beforeEach(function () {
    seedNigeria();
    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.auth_max_age' => 3600,
    ]);
});

// ── Signature & payload validation ──────────────────────────────────────────

test('valid initData for an unlinked account reports it is not linked', function () {
    $this->postJson('/api/v1/telegram/auth', [
        'init_data' => telegramInitData(['id' => 111222]),
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.linked', false)
        ->assertJsonPath('data.telegram_user.id', 111222);

    expect(TelegramIdentity::count())->toBe(0);
});

test('a tampered hash is rejected', function () {
    $this->postJson('/api/v1/telegram/auth', [
        'init_data' => telegramInitData(hashOverride: str_repeat('0', 64)),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);
});

test('a hash signed with a different bot token is rejected', function () {
    $this->postJson('/api/v1/telegram/auth', [
        'init_data' => telegramInitData(botToken: 'attacker-token'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);
});

test('stale initData beyond the max age is rejected', function () {
    config(['services.telegram.auth_max_age' => 600]);

    $this->postJson('/api/v1/telegram/auth', [
        'init_data' => telegramInitData(authDate: time() - 5000),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);
});

test('missing hash is rejected', function () {
    $this->postJson('/api/v1/telegram/auth', [
        'init_data' => telegramInitData(withHash: false),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);
});

test('missing user payload is rejected', function () {
    $initData = telegramInitData();
    // Strip the user= pair but keep a (now mismatched) valid signature shape.
    parse_str($initData, $fields);
    unset($fields['user']);

    $pairs = [];

    foreach ($fields as $key => $value) {
        $pairs[] = $key.'='.(string) $value;
    }

    sort($pairs, SORT_STRING);

    $secret = hash_hmac('sha256', 'test-bot-token', 'WebAppData', true);
    $fields['hash'] = hash_hmac('sha256', implode("\n", $pairs), $secret);

    $this->postJson('/api/v1/telegram/auth', ['init_data' => http_build_query($fields)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);
});

test('a replayed initData cannot mint a second token', function () {
    $user = apiUser(['country' => 'NG']);

    TelegramIdentity::create([
        'user_id' => $user->id,
        'telegram_user_id' => 424242,
    ]);

    $initData = telegramInitData(['id' => 424242], authDate: time() - 10);

    $this->postJson('/api/v1/telegram/auth', ['init_data' => $initData])
        ->assertOk()
        ->assertJsonPath('data.linked', true);

    $this->postJson('/api/v1/telegram/auth', ['init_data' => $initData])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);
});

test('a consumed initData cannot be used to link afterwards', function () {
    $user = apiUser(['country' => 'NG']);
    $other = apiUser(['country' => 'NG']);

    TelegramIdentity::create([
        'user_id' => $user->id,
        'telegram_user_id' => 535353,
    ]);

    $initData = telegramInitData(['id' => 535353]);

    // Sign in with it (consumes it)…
    $this->postJson('/api/v1/telegram/auth', ['init_data' => $initData])->assertOk();

    // …then try to reuse the same payload to bind it to another account.
    $this->actingAs($other, 'sanctum')
        ->postJson('/api/v1/telegram/link', ['init_data' => $initData])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);
});

test('the full mini-app flow: unlinked check, password login, link, instant sign-in', function () {
    $user = apiUser(['country' => 'NG', 'password' => 'SuperSecret123!']);

    // One initData for the whole onboarding session.
    $initData = telegramInitData(['id' => 616161], authDate: time() - 15);

    $this->postJson('/api/v1/telegram/auth', ['init_data' => $initData])
        ->assertOk()
        ->assertJsonPath('data.linked', false);

    $login = $this->postJson('/api/v1/login', [
        'login' => $user->email,
        'password' => 'SuperSecret123!',
    ])->assertOk();

    $this->withHeaders(['Authorization' => 'Bearer '.$login->json('data.token')])
        ->postJson('/api/v1/telegram/link', ['init_data' => $initData])
        ->assertOk()
        ->assertJsonPath('success', true);

    // Next time the Mini App opens, sign-in is instant — fresh initData,
    // same Telegram account.
    $this->postJson('/api/v1/telegram/auth', [
        'init_data' => telegramInitData(['id' => 616161], authDate: time() + 1),
    ])
        ->assertOk()
        ->assertJsonPath('data.linked', true);
});

// ── Linking ───────────────────────────────────────────────────────────────────

test('an authenticated user can link their Telegram account and then sign in with it', function () {
    $user = apiUser(['country' => 'NG']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/telegram/link', ['init_data' => telegramInitData(['id' => 555001], authDate: time() - 5)])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.telegram.username', 'ada');

    expect(TelegramIdentity::where('user_id', $user->id)->count())->toBe(1);

    // Sign in fresh via Telegram now that the identity is linked.
    $auth = $this->postJson('/api/v1/telegram/auth', [
        'init_data' => telegramInitData(['id' => 555001], authDate: time() - 1),
    ])->assertOk()->assertJsonPath('data.linked', true);

    $token = $auth->json('data.token');

    expect($token)->not->toBeNull()
        ->and(PersonalAccessToken::where('name', TelegramAuthService::TOKEN_NAME)->count())->toBe(1);

    // The issued token works against the regular API.
    $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.telegram.linked', true);
});

test('one Telegram account cannot be linked to two users', function () {
    $first = apiUser(['country' => 'NG']);
    $second = apiUser(['country' => 'NG']);

    TelegramIdentity::create([
        'user_id' => $first->id,
        'telegram_user_id' => 999111,
        'username' => 'taken',
    ]);

    $this->actingAs($second, 'sanctum')
        ->postJson('/api/v1/telegram/link', ['init_data' => telegramInitData(['id' => 999111, 'username' => 'taken'])])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);

    expect(TelegramIdentity::where('telegram_user_id', 999111)->count())->toBe(1);
});

test('a user already linked to a different Telegram account must unlink first', function () {
    $user = apiUser(['country' => 'NG']);

    TelegramIdentity::create([
        'user_id' => $user->id,
        'telegram_user_id' => 121212,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/telegram/link', ['init_data' => telegramInitData(['id' => 343434])])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['init_data']);
});

test('a user can unlink their Telegram account', function () {
    $user = apiUser(['country' => 'NG']);

    TelegramIdentity::create([
        'user_id' => $user->id,
        'telegram_user_id' => 767676,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/telegram/unlink')
        ->assertOk();

    expect(TelegramIdentity::where('user_id', $user->id)->count())->toBe(0);
});

test('linking requires authentication', function () {
    $this->postJson('/api/v1/telegram/link', ['init_data' => telegramInitData()])
        ->assertStatus(401);

    $this->postJson('/api/v1/telegram/unlink')
        ->assertStatus(401);
});

test('a suspended linked user cannot sign in via Telegram', function () {
    $user = apiUser(['country' => 'NG', 'status' => \App\Enums\UserStatus::SUSPENDED]);

    TelegramIdentity::create([
        'user_id' => $user->id,
        'telegram_user_id' => 313131,
    ]);

    $this->postJson('/api/v1/telegram/auth', [
        'init_data' => telegramInitData(['id' => 313131]),
    ])->assertStatus(403);
});
