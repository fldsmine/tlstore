<?php

use App\Models\Otp;
use Illuminate\Support\Facades\Hash;

test('forgot password answers generically for unknown emails', function () {
    $this->postJson('/api/v1/password/forgot', ['email' => 'ghost@example.com'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Otp::count())->toBe(0);
});

test('the full reset flow updates the password and revokes tokens', function () {
    $user = apiUser(['password' => 'OldPassword123!']);
    $user->createToken('old-device');

    $this->postJson('/api/v1/password/forgot', ['email' => $user->email])->assertOk();

    $this->postJson('/api/v1/password/reset', [
        'email' => $user->email,
        'code' => '123456', // fixed OTP in the testing environment
        'password' => 'NewPassword456!',
    ])->assertOk()->assertJsonPath('success', true);

    expect(Hash::check('NewPassword456!', $user->refresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});

test('reset rejects an invalid otp code', function () {
    $user = apiUser();

    $this->postJson('/api/v1/password/forgot', ['email' => $user->email])->assertOk();

    $this->postJson('/api/v1/password/reset', [
        'email' => $user->email,
        'code' => '000000',
        'password' => 'NewPassword456!',
    ])->assertStatus(422)->assertJsonValidationErrors(['code']);
});

test('reset validates the payload', function () {
    $this->postJson('/api/v1/password/reset', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'code', 'password']);
});
