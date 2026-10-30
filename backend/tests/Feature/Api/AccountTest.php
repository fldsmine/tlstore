<?php

use App\Models\Coupon;
use App\Models\Otp;
use App\Enums\CouponType;
use Illuminate\Support\Facades\Hash;

test('users can verify their email with the otp code', function () {
    $user = apiUser(['email_verified_at' => null]);

    app(\App\Services\OtpService::class)->send($user, null, \App\Enums\OtpType::EMAIL_VERIFICATION);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/email/verify', ['code' => '123456'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('email verification rejects a wrong code', function () {
    $user = apiUser(['email_verified_at' => null]);

    app(\App\Services\OtpService::class)->send($user, null, \App\Enums\OtpType::EMAIL_VERIFICATION);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/email/verify', ['code' => '999999'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('verification codes can be resent but are rate limited', function () {
    $user = apiUser(['email_verified_at' => null]);

    foreach (range(1, 3) as $i) {
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/email/resend')->assertOk();
    }

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/email/resend')
        ->assertStatus(429);

    expect(Otp::where('user_id', $user->id)->count())->toBeGreaterThanOrEqual(1);
});

test('users can change their password with the current one', function () {
    $user = apiUser(['password' => 'OldPassword123!']);
    $keep = $user->createToken('current-device');
    $user->createToken('other-device');

    $this->withHeader('Authorization', 'Bearer '.$keep->plainTextToken)
        ->putJson('/api/v1/account/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
        ->assertOk();

    expect(Hash::check('NewPassword456!', $user->refresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('current-device');
});

test('changing password requires the correct current one', function () {
    $user = apiUser(['password' => 'OldPassword123!']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/account/password', [
            'current_password' => 'wrong',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('users can set a transaction pin once', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/account/pin', ['pin' => '1234', 'pin_confirmation' => '1234'])
        ->assertCreated();

    expect(Hash::check('1234', $user->refresh()->transaction_pin))->toBeTrue();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/account/pin', ['pin' => '5678', 'pin_confirmation' => '5678'])
        ->assertStatus(422);
});

test('users can change their pin with the current one', function () {
    $user = apiUser(['transaction_pin' => '1234']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/account/pin', [
            'current_pin' => '1234',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])
        ->assertOk();

    expect(Hash::check('5678', $user->refresh()->transaction_pin))->toBeTrue();
});

test('changing pin rejects an incorrect current pin', function () {
    $user = apiUser(['transaction_pin' => '1234']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/account/pin', [
            'current_pin' => '0000',
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_pin']);
});

test('users can reset a forgotten pin with a vendor token', function () {
    $user = apiUser(['transaction_pin' => '1234', 'country' => 'NG']);
    $coupon = Coupon::factory()->create(['type' => CouponType::PIN_RESET, 'region' => 'nigeria']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/account/pin/reset', [
            'token' => $coupon->code,
            'pin' => '4321',
            'pin_confirmation' => '4321',
        ])
        ->assertOk();

    expect(Hash::check('4321', $user->refresh()->transaction_pin))->toBeTrue()
        ->and($coupon->refresh()->used_at)->not->toBeNull();
});

test('pin reset rejects an invalid token', function () {
    $user = apiUser(['transaction_pin' => '1234']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/account/pin/reset', [
            'token' => 'NOT-A-REAL-TOKEN',
            'pin' => '4321',
            'pin_confirmation' => '4321',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});
