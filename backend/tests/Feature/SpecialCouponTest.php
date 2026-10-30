<?php

use App\Enums\CouponType;
use App\Models\CloudStoragePlan;
use App\Models\CloudStorageTopUp;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\User;
use App\Services\CloudActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create packages
    $package1 = Package::create([
        'name' => 'Diamond eSIM',
        'tier_level' => 1,
        'enable_cloud' => true,
        'cloud_calls' => false,
        'cloud_sms' => true,
        'cloud_contact_limit' => 0,
    ]);

    $package2 = Package::create([
        'name' => 'Royal eSIM',
        'tier_level' => 2,
        'enable_cloud' => true,
        'cloud_calls' => true,
        'cloud_sms' => true,
        'cloud_contact_limit' => 10,
    ]);

    // Create tier-specific cloud storage plans
    CloudStoragePlan::create([
        'name' => 'Tier 1 Special Storage',
        'description' => 'Special coupon storage for Diamond eSIM users.',
        'extra_contacts' => 4,
        'tier_level' => 1,
        'is_active' => true,
        'sort_order' => 100,
    ]);

    CloudStoragePlan::create([
        'name' => 'Tier 2 Special Storage',
        'description' => 'Special coupon storage for Royal eSIM users.',
        'extra_contacts' => 7,
        'tier_level' => 2,
        'is_active' => true,
        'sort_order' => 101,
    ]);
});

test('storage activation requires a code', function () {
    $user = User::factory()->create(['package_id' => Package::where('tier_level', 1)->first()->id]);

    $service = app(CloudActivationService::class);

    expect(fn () => $service->activateStorage($user, ''))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('storage activation rejects invalid code', function () {
    $user = User::factory()->create(['package_id' => Package::where('tier_level', 1)->first()->id]);

    $service = app(CloudActivationService::class);

    expect(fn () => $service->activateStorage($user, 'INVALID_CODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('storage activation rejects code for wrong tier', function () {
    // User has tier 1, but code is for tier 2 plan
    $user = User::factory()->create(['package_id' => Package::where('tier_level', 1)->first()->id]);
    
    $tier2Plan = CloudStoragePlan::where('tier_level', 2)->first();
    $coupon = Coupon::create([
        'code' => 'TIER2CODE',
        'code_hash' => Coupon::hashCode('TIER2CODE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $tier2Plan->id,
        'expires_at' => now()->addDays(90),
    ]);

    $service = app(CloudActivationService::class);

    expect(fn () => $service->activateStorage($user, 'TIER2CODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('storage activation rejects expired code', function () {
    $user = User::factory()->create(['package_id' => Package::where('tier_level', 1)->first()->id]);
    
    $tier1Plan = CloudStoragePlan::where('tier_level', 1)->first();
    $coupon = Coupon::create([
        'code' => 'EXPIREDCODE',
        'code_hash' => Coupon::hashCode('EXPIREDCODE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $tier1Plan->id,
        'expires_at' => now()->subDay(),
    ]);

    $service = app(CloudActivationService::class);

    expect(fn () => $service->activateStorage($user, 'EXPIREDCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('storage activation rejects already activated code', function () {
    $user = User::factory()->create(['package_id' => Package::where('tier_level', 1)->first()->id]);
    
    $tier1Plan = CloudStoragePlan::where('tier_level', 1)->first();
    $coupon = Coupon::create([
        'code' => 'DUPCODE',
        'code_hash' => Coupon::hashCode('DUPCODE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $tier1Plan->id,
        'expires_at' => now()->addDays(90),
    ]);

    $service = app(CloudActivationService::class);
    
    // First activation succeeds
    $service->activateStorage($user, 'DUPCODE');

    // Second activation should fail
    expect(fn () => $service->activateStorage($user, 'DUPCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('storage activation creates cloud storage top up for tier 1', function () {
    $user = User::factory()->create(['package_id' => Package::where('tier_level', 1)->first()->id]);
    
    $tier1Plan = CloudStoragePlan::where('tier_level', 1)->first();
    $coupon = Coupon::create([
        'code' => 'TIER1CODE',
        'code_hash' => Coupon::hashCode('TIER1CODE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $tier1Plan->id,
        'expires_at' => now()->addDays(90),
    ]);

    $service = app(CloudActivationService::class);
    $service->activateStorage($user, 'TIER1CODE');

    // Check coupon is bound to user
    expect($coupon->refresh()->user_id)->toBe($user->id);
    expect($coupon->used_at)->toBeNull();

    // Check CloudStorageTopUp was created with correct extra_contacts
    $topUp = CloudStorageTopUp::where('user_id', $user->id)->first();
    expect($topUp)->not->toBeNull();
    expect($topUp->extra_contacts)->toBe(4);
    expect($topUp->coupon_id)->toBe($coupon->id);
    expect($topUp->cloud_storage_plan_id)->toBe($tier1Plan->id);
});

test('storage activation creates cloud storage top up for tier 2', function () {
    $user = User::factory()->create(['package_id' => Package::where('tier_level', 2)->first()->id]);
    
    $tier2Plan = CloudStoragePlan::where('tier_level', 2)->first();
    $coupon = Coupon::create([
        'code' => 'TIER2CODE',
        'code_hash' => Coupon::hashCode('TIER2CODE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $tier2Plan->id,
        'expires_at' => now()->addDays(90),
    ]);

    $service = app(CloudActivationService::class);
    $service->activateStorage($user, 'TIER2CODE');

    // Check coupon is bound to user
    expect($coupon->refresh()->user_id)->toBe($user->id);
    expect($coupon->used_at)->toBeNull();

    // Check CloudStorageTopUp was created with correct extra_contacts
    $topUp = CloudStorageTopUp::where('user_id', $user->id)->first();
    expect($topUp)->not->toBeNull();
    expect($topUp->extra_contacts)->toBe(7);
    expect($topUp->coupon_id)->toBe($coupon->id);
    expect($topUp->cloud_storage_plan_id)->toBe($tier2Plan->id);
});

test('storage activation requires package', function () {
    $user = User::factory()->create(['package_id' => null]);

    $tier1Plan = CloudStoragePlan::where('tier_level', 1)->first();
    $coupon = Coupon::create([
        'code' => 'NOPKGCODE',
        'code_hash' => Coupon::hashCode('NOPKGCODE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $tier1Plan->id,
        'expires_at' => now()->addDays(90),
    ]);

    $service = app(CloudActivationService::class);

    expect(fn () => $service->activateStorage($user, 'NOPKGCODE'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('code hash verification works correctly', function () {
    $code = 'TEST123';
    $hash = Coupon::hashCode($code);

    $coupon = Coupon::create([
        'code' => $code,
        'code_hash' => $hash,
        'type' => CouponType::STORAGE_ACTIVATION,
        'expires_at' => now()->addDays(90),
    ]);

    expect($coupon->verifyCode($code))->toBeTrue();
    expect($coupon->verifyCode('wrong_code'))->toBeFalse();
    expect($coupon->verifyCode('test123'))->toBeTrue(); // Hash lookup is case-insensitive
});
