<?php

use App\Models\User;
use App\Support\Onboarding;

test('email and transaction PIN complete onboarding without requiring eSIM activation', function () {
    $user = User::factory()->create([
        'package_id' => null,
        'email_verified_at' => now(),
        'transaction_pin' => bcrypt('1234'),
    ]);

    expect(Onboarding::nextRoute($user))->toBeNull();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No eSIM package yet');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.user.onboarding.activated', false)
        ->assertJsonPath('data.user.onboarding.complete', true);
});

test('email verification and PIN remain required before the dashboard', function () {
    config(['settings.app.enable_email_verification' => true]);
    $unverified = User::factory()->unverified()->create([
        'transaction_pin' => bcrypt('1234'),
        'package_id' => null,
    ]);

    expect(Onboarding::nextRoute($unverified))->toBe('auth.verify-email');

    $verifiedWithoutPin = User::factory()->create([
        'transaction_pin' => null,
        'package_id' => null,
    ]);

    expect(Onboarding::nextRoute($verifiedWithoutPin))->toBe('auth.setup-pin');
});
