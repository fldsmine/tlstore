<?php

use App\Enums\CouponType;
use App\Models\Coupon;

test('website status reports expiry and renewal options', function () {
    $user = activatedUser(['website_expires_at' => now()->addDays(5)]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/website')
        ->assertOk()
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.renewal_mode', 'online')
        ->assertJsonPath('data.duration_days', 30);
});

test('an expired website reports inactive', function () {
    $user = activatedUser(['website_expires_at' => now()->subDay()]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/website')
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

test('online renewal is unavailable without a configured gateway', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/website/renew')
        ->assertStatus(422);
});

test('code renewal is blocked while the site runs in online mode', function () {
    config(['settings.website.renewal_mode' => 'code']);

    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/website/renew')
        ->assertStatus(422);
});

test('a website-renewal code extends the window', function () {
    $user = activatedUser(['website_expires_at' => now()->addDays(2)]);
    $coupon = Coupon::factory()->create([
        'type' => CouponType::WEBSITE_RENEWAL,
        'region' => 'nigeria',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/website/renew/code', ['code' => $coupon->code])
        ->assertOk();

    // 2 days left + 30-day duration.
    expect($user->refresh()->website_expires_at->greaterThan(now()->addDays(29)))->toBeTrue();
    expect($coupon->refresh()->used_at)->not->toBeNull();
});

test('renewal codes require an existing website', function () {
    $user = activatedUser(); // no website yet
    $coupon = Coupon::factory()->create([
        'type' => CouponType::WEBSITE_RENEWAL,
        'region' => 'nigeria',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/website/renew/code', ['code' => $coupon->code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});
