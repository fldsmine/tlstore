<?php

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\User;

beforeEach(fn () => seedNigeria());

test('anyone can list active packages with prices', function () {
    Package::factory()->count(2)->create();
    Package::factory()->inactive()->create();

    $this->getJson('/api/v1/packages')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.packages');

    // Guests get Nigerian pricing by default.
    $this->getJson('/api/v1/packages')
        ->assertJsonPath('data.packages.0.price_usd', 10);
});

test('users activate their account with a vendor token', function () {
    $user = apiUser(['country' => 'NG']);
    $package = Package::factory()->create(['call_id_limit' => 1]);
    $coupon = Coupon::factory()->create([
        'type' => CouponType::ACTIVATION,
        'package_id' => $package->id,
        'region' => 'nigeria',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/activate', ['package_id' => $package->id, 'token' => $coupon->code])
        ->assertOk()
        ->assertJsonPath('data.package.id', $package->id);

    expect($user->refresh()->package_id)->toBe($package->id)
        ->and($coupon->refresh()->used_at)->not->toBeNull()
        ->and($user->allocatedNumbers()->count())->toBe(1);
});

test('activation rejects an already used token', function () {
    $user = apiUser(['country' => 'NG']);
    $package = Package::factory()->create();
    $coupon = Coupon::factory()->used()->create([
        'type' => CouponType::ACTIVATION,
        'package_id' => $package->id,
        'region' => 'nigeria',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/activate', ['package_id' => $package->id, 'token' => $coupon->code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);

    expect($user->refresh()->package_id)->toBeNull();
});

test('activation rejects a token minted for another package', function () {
    $user = apiUser(['country' => 'NG']);
    $otherPackage = Package::factory()->create();
    $package = Package::factory()->create();
    $coupon = Coupon::factory()->create([
        'type' => CouponType::ACTIVATION,
        'package_id' => $otherPackage->id,
        'region' => 'nigeria',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/activate', ['package_id' => $package->id, 'token' => $coupon->code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('users cannot redeem a token they issued themselves', function () {
    $vendor = apiUser(['country' => 'NG', 'is_agent' => true]);
    $package = Package::factory()->create();
    $coupon = Coupon::factory()->create([
        'type' => CouponType::ACTIVATION,
        'package_id' => $package->id,
        'agent_id' => $vendor->id,
        'region' => 'nigeria',
    ]);

    $this->actingAs($vendor, 'sanctum')
        ->postJson('/api/v1/activate', ['package_id' => $package->id, 'token' => $coupon->code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('activated users cannot activate again', function () {
    $user = activatedUser(['country' => 'NG']);
    $package = Package::factory()->create();
    $coupon = Coupon::factory()->create([
        'type' => CouponType::ACTIVATION,
        'package_id' => $package->id,
        'region' => 'nigeria',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/activate', ['package_id' => $package->id, 'token' => $coupon->code])
        ->assertStatus(422);
});

test('activated users can upgrade with an upgrade token', function () {
    $current = Package::factory()->create(['tier_level' => 1]);
    $user = apiUser(['country' => 'NG', 'package_id' => $current->id]);
    $better = Package::factory()->create(['tier_level' => 2]);
    $coupon = Coupon::factory()->create([
        'type' => CouponType::UPGRADE,
        'package_id' => $better->id,
        'region' => 'nigeria',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/upgrade', ['package_id' => $better->id, 'token' => $coupon->code])
        ->assertOk();

    expect($user->refresh()->package_id)->toBe($better->id);
});

test('upgrade rejects a package at or below the current tier', function () {
    $current = Package::factory()->create(['tier_level' => 2]);
    $user = apiUser(['country' => 'NG', 'package_id' => $current->id]);
    $lower = Package::factory()->create(['tier_level' => 1]);
    $coupon = Coupon::factory()->create([
        'type' => CouponType::UPGRADE,
        'package_id' => $lower->id,
        'region' => 'nigeria',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/upgrade', ['package_id' => $lower->id, 'token' => $coupon->code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['package_id']);
});
