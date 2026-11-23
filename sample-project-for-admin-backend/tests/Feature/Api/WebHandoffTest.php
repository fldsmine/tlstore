<?php

use App\Models\User;

const GATE_TEST_SECRET = 'unit-test-gate-secret-0123456789abcdef0123456789abcdef';

function gateCookieName(): string
{
    return (string) config('services.gate.cookie', 'app_gate');
}

function enableAppGate(): void
{
    config()->set('services.gate.secret', GATE_TEST_SECRET);
}

function cookieFromResponse($response): ?Symfony\Component\HttpFoundation\Cookie
{
    return collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === gateCookieName());
}

/*
|--------------------------------------------------------------------------
| Access gate
|--------------------------------------------------------------------------
*/

test('gate is inactive when APP_GATE_SECRET is empty', function () {
    $user = User::factory()->create();

    $this->get('/activate')->assertRedirect(route('auth.login'));
    $this->actingAs($user)->get('/activate')->assertOk();
});

test('gated section drops requests without the cookie (empty 403)', function () {
    enableAppGate();

    $response = $this->get('/activate');

    $response->assertStatus(403);
    expect($response->getContent())->toBe('');
});

test('gated section drops authenticated requests without the cookie', function () {
    enableAppGate();

    $response = $this->actingAs(User::factory()->create())->get('/activate');

    $response->assertStatus(403);
    expect($response->getContent())->toBe('');
});

test('guest with a valid cookie reaches the auth layer (redirect to login)', function () {
    enableAppGate();

    // Unencrypted: the gate cookie is excluded from cookie encryption.
    $this->withUnencryptedCookies([gateCookieName() => GATE_TEST_SECRET])
        ->get('/activate')
        ->assertRedirect(route('auth.login'));
});

test('authenticated request with a valid cookie passes', function () {
    enableAppGate();

    $this->actingAs(User::factory()->create())
        ->withUnencryptedCookies([gateCookieName() => GATE_TEST_SECRET])
        ->get('/activate')
        ->assertOk();
});

test('public pages mint the gate cookie (httpOnly)', function () {
    enableAppGate();

    $response = $this->get(route('auth.login'));

    $response->assertOk();

    $cookie = cookieFromResponse($response);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getValue())->toBe(GATE_TEST_SECRET)
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getPath())->toBe('/');
});

test('public pages mint the gate cookie securely over HTTPS', function () {
    enableAppGate();

    // Symfony derives HTTPS from the URL scheme, so request an https URL.
    $response = $this->get(str_replace('http://', 'https://', route('auth.login')));

    expect(cookieFromResponse($response)?->isSecure())->toBeTrue();
});

test('a minted cookie on a public page unlocks later gated loads', function () {
    enableAppGate();

    // First visit: the WebView receives Set-Cookie on a public page.
    $response = $this->get(route('auth.login'));
    $response->assertOk();

    $minted = cookieFromResponse($response);
    expect($minted?->getValue())->toBe(GATE_TEST_SECRET);

    // Later visit: the browser replays the stored cookie into the gated section.
    $this->actingAs(User::factory()->create())
        ->withUnencryptedCookies([gateCookieName() => $minted->getValue()])
        ->get('/activate')
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Mini App → Livewire web-session handoff
|--------------------------------------------------------------------------
*/

test('web-session logs the sanctum user into the web guard', function () {
    enableAppGate();

    $user = User::factory()->create();
    $token = $user->createToken('mini-app')->plainTextToken;

    $response = $this->postJson('/api/v1/web-session', [], [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ]);

    // Plain cookie — the gate value is excluded from cookie encryption.
    $response->assertOk()->assertPlainCookie(gateCookieName(), GATE_TEST_SECRET);

    // The WebView replays the Set-Cookie values (session + gate) on the
    // next navigation, landing the same user on the Livewire app.
    // (session_name() outside a request is PHP's default — use the config.)
    $sessionCookie = (string) config('session.cookie');

    $session = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === $sessionCookie);

    expect($session)->not->toBeNull();

    $this->withUnencryptedCookies([
        $sessionCookie => $session->getValue(),      // encrypted blob, as the browser stores it
        gateCookieName() => GATE_TEST_SECRET,
    ])->get('/activate')->assertOk();

    expect(auth('web')->id())->toBe($user->id);
});

test('web-session rejects unauthenticated requests', function () {
    enableAppGate();

    $this->postJson('/api/v1/web-session')->assertUnauthorized();
});
