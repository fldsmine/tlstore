<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(fn () => seedNigeria());

test('guests can register and receive a bearer token', function () {
    $response = $this->postJson('/api/v1/register', [
        'full_name' => 'Ada Lovelace',
        'username' => 'adalovelace',
        'email' => 'ada@example.com',
        'phone' => '08012345678',
        'country' => 'NG',
        'password' => 'SuperSecret123!',
        'agree_terms' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.username', 'adalovelace')
        ->assertJsonPath('data.verification_required', true)
        ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

    expect(User::where('email', 'ada@example.com')->exists())->toBeTrue()
        ->and(PersonalAccessToken::count())->toBe(1);
});

test('registration keeps the referral link when a referrer is given', function () {
    $referrer = apiUser(['country' => 'NG']);

    $this->postJson('/api/v1/register', [
        'full_name' => 'Bob Referee',
        'username' => 'bobreferee',
        'email' => 'bob@example.com',
        'phone' => '08012345679',
        'country' => 'NG',
        'password' => 'SuperSecret123!',
        'agree_terms' => true,
        'referral_code' => $referrer->username,
    ])->assertCreated();

    $user = User::where('email', 'bob@example.com')->first();

    expect($user->referrer_id)->toBe($referrer->id)
        ->and($referrer->referrals()->count())->toBe(1);
});

test('registration validates required fields', function () {
    $this->postJson('/api/v1/register', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['full_name', 'username', 'email', 'phone', 'country', 'password', 'agree_terms']);
});

test('registration rejects duplicate email and username', function () {
    $existing = apiUser(['username' => 'taken', 'email' => 'taken@example.com']);

    $this->postJson('/api/v1/register', [
        'full_name' => 'Dup User',
        'username' => 'taken',
        'email' => 'taken@example.com',
        'phone' => '08099999999',
        'country' => 'NG',
        'password' => 'SuperSecret123!',
        'agree_terms' => true,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'email']);
});

test('users can log in with email or username', function () {
    $user = apiUser(['password' => 'SuperSecret123!']);

    $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'SuperSecret123!'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user']]);

    $this->postJson('/api/v1/login', ['login' => $user->username, 'password' => 'SuperSecret123!'])
        ->assertOk();
});

test('login rejects bad credentials with 401', function () {
    $user = apiUser(['password' => 'SuperSecret123!']);

    $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'wrong-password'])
        ->assertStatus(401)
        ->assertJsonPath('success', false);
});

test('suspended users receive a support-only ticket token on successful credential verification', function () {
    Mail::fake();
    $user = apiUser([
        'first_name' => 'Ada',
        'last_name' => 'Okafor',
        'password' => 'SuperSecret123!',
        'status' => 'suspended',
    ]);
    $previousFullToken = $user->createToken('previous-device-session')->plainTextToken;

    $response = $this->postJson('/api/v1/login', [
        'login' => $user->email,
        'password' => 'SuperSecret123!',
    ])->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', 'Ada Okafor')
        ->assertJsonPath('data.user.email', $user->email);

    $supportToken = $response->json('data.support_token');
    expect($supportToken)->not->toBeEmpty()
        ->and(PersonalAccessToken::query()->where('name', 'previous-device-session')->exists())->toBeFalse();

    Auth::forgetGuards();
    $this->withToken($previousFullToken)
        ->getJson('/api/v1/user')
        ->assertUnauthorized();

    Auth::forgetGuards();
    $this->withToken($supportToken)
        ->getJson('/api/v1/support-tickets')
        ->assertOk()
        ->assertJsonPath('data.user.name', 'Ada Okafor')
        ->assertJsonPath('data.user.email', $user->email);

    $this->withToken($supportToken)
        ->postJson('/api/v1/support-tickets', [
            'subject' => 'Help with my account',
            'category' => 'account',
            'message' => 'I need help reviewing the suspension.',
        ])
        ->assertCreated();

    $this->withToken($supportToken)
        ->getJson('/api/v1/user')
        ->assertForbidden();
});

test('a suspended full-access session is rotated to a support-only token', function () {
    $user = apiUser(['status' => 'suspended']);
    $fullToken = $user->createToken('previous-mobile-session')->plainTextToken;

    $response = $this->withToken($fullToken)
        ->getJson('/api/v1/user')
        ->assertForbidden()
        ->assertJsonPath('data.user.id', $user->id);

    $supportToken = $response->json('data.support_token');
    expect($supportToken)->not->toBeEmpty()
        ->and(PersonalAccessToken::query()->where('name', 'previous-mobile-session')->exists())->toBeFalse();

    Auth::forgetGuards();
    $this->withToken($fullToken)
        ->getJson('/api/v1/support-tickets')
        ->assertUnauthorized();

    Auth::forgetGuards();
    $this->withToken($supportToken)
        ->getJson('/api/v1/support-tickets')
        ->assertOk();
});

test('login is rate limited after repeated failures', function () {
    $user = apiUser(['password' => 'SuperSecret123!']);

    foreach (range(1, 6) as $i) {
        $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'nope']);
    }

    $this->postJson('/api/v1/login', ['login' => $user->email, 'password' => 'SuperSecret123!'])
        ->assertStatus(429);
});

test('authenticated users can fetch their own profile', function () {
    $user = activatedUser(['transaction_pin' => bcrypt('1234')]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.onboarding.complete', true);

    // Sensitive fields must never leak.
    $payload = $this->actingAs($user, 'sanctum')->getJson('/api/v1/user')->json();
    expect(json_encode($payload))->not->toContain('password');
});

test('guests are rejected from protected endpoints', function () {
    $this->getJson('/api/v1/user')->assertStatus(401);
    $this->getJson('/api/v1/wallets')->assertStatus(401);
    $this->getJson('/api/v1/transactions')->assertStatus(401);
});

test('logout revokes only the current token', function () {
    $user = apiUser();
    $user->createToken('other-device');

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/logout')->assertOk();

    // actingAs() binds the user to the Sanctum guard for the rest of the test;
    // drop it so the next request authenticates purely via the bearer token.
    \Illuminate\Support\Facades\Auth::forgetGuards();

    // actingAs() uses a transient token; create a real one to verify deletion semantics.
    $token = $user->createToken('mobile');
    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->postJson('/api/v1/logout')
        ->assertOk();

    expect(PersonalAccessToken::where('name', 'mobile')->count())->toBe(0);
});
