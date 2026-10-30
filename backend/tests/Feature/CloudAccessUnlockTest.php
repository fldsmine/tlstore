<?php

use App\Enums\CouponType;
use App\Models\CloudNumber;
use App\Models\CloudStoragePlan;
use App\Models\CloudStorageTopUp;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\User;
use App\Services\CloudActivationService;
use App\Services\CloudCallService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mirrors the real seeder: Diamond (tier 1) has the inbox but NOT cloud calling;
    // Royal (tier 2) has the full cloud feature set.
    Package::create([
        'name' => 'Diamond eSIM',
        'tier_level' => 1,
        'enable_cloud' => true,
        'cloud_calls' => false,
        'cloud_sms' => true,
        'cloud_contact_limit' => 0,
    ]);

    Package::create([
        'name' => 'Royal eSIM',
        'tier_level' => 2,
        'enable_cloud' => true,
        'cloud_calls' => true,
        'cloud_sms' => true,
        'cloud_contact_limit' => 10,
    ]);

    CloudStoragePlan::create([
        'name' => 'Tier 1 Special Storage',
        'extra_contacts' => 4,
        'tier_level' => 1,
        'is_active' => true,
        'sort_order' => 100,
    ]);

    CloudStoragePlan::create([
        'name' => 'Tier 2 Special Storage',
        'extra_contacts' => 7,
        'tier_level' => 2,
        'is_active' => true,
        'sort_order' => 101,
    ]);
});

function tierUser(int $tier): User
{
    return User::factory()->create([
        'package_id' => Package::where('tier_level', $tier)->first()->id,
    ]);
}

function specialCouponFor(int $tier, ?User $boundTo = null): Coupon
{
    $plan = CloudStoragePlan::where('tier_level', $tier)->first();

    return Coupon::create([
        'code' => 'TIER'.$tier.'SPECIAL',
        'code_hash' => Coupon::hashCode('TIER'.$tier.'SPECIAL'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $plan->id,
        'expires_at' => now()->addDays(90),
        'user_id' => $boundTo?->id,
    ]);
}

test('tier 1 user without a special coupon cannot cloud call or hold contacts', function () {
    $user = tierUser(1);

    // The package includes the inbox but not calling.
    expect($user->canAccessCloud())->toBeTrue();
    expect($user->canCloudSms())->toBeTrue();
    expect($user->canCloudCall())->toBeFalse();
    expect($user->cloudContactLimit())->toBe(0);

    $number = CloudNumber::create(['name' => 'Sponsor', 'line_number' => '+16980000001', 'is_active' => true]);

    expect(fn () => app(CloudCallService::class)->addNumber($user, $number))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('activating a tier 1 special coupon unlocks cloud calling and the contact slots', function () {
    $user = tierUser(1);
    $coupon = specialCouponFor(1);

    app(CloudActivationService::class)->activateStorage($user, $coupon->code);

    $user->refresh();

    expect($user->canCloudCall())->toBeTrue();
    expect($user->canCloudSms())->toBeTrue();
    expect($user->cloudContactLimit())->toBe(4);

    // The granted slots are usable: the user can add sponsor contacts.
    $number = CloudNumber::create(['name' => 'Sponsor', 'line_number' => '+16980000001', 'is_active' => true]);
    app(CloudCallService::class)->addNumber($user, $number);

    expect($user->cloudNumbers()->count())->toBe(1);
});

test('the special coupon contact quota is enforced for tier 1 users', function () {
    $user = tierUser(1);
    $coupon = specialCouponFor(1);

    app(CloudActivationService::class)->activateStorage($user, $coupon->code);
    $user->refresh();

    $service = app(CloudCallService::class);

    foreach (range(1, 4) as $i) {
        $service->addNumber($user, CloudNumber::create(['name' => 'Sponsor '.$i, 'line_number' => '+1698000000'.$i, 'is_active' => true]));
    }

    expect($user->cloudNumbers()->count())->toBe(4);

    // The 5th contact exceeds the +4 granted by the coupon.
    expect(fn () => $service->addNumber($user, CloudNumber::create(['name' => 'Sponsor 5', 'line_number' => '+16980000005', 'is_active' => true])))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('cloud access lapses when the special coupon storage expires', function () {
    $user = tierUser(1);
    $coupon = specialCouponFor(1);

    app(CloudActivationService::class)->activateStorage($user, $coupon->code);
    $user->refresh();

    expect($user->canCloudCall())->toBeTrue();

    // A storage pack with a duration expires — the unlock lapses with it.
    CloudStorageTopUp::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);
    $user->refresh();

    expect($user->canCloudCall())->toBeFalse();
    expect($user->cloudContactLimit())->toBe(0);
});

test('tier 2 users keep package-driven cloud access without any coupon', function () {
    $user = tierUser(2);

    expect($user->canAccessCloud())->toBeTrue();
    expect($user->canCloudCall())->toBeTrue();
    expect($user->canCloudSms())->toBeTrue();
    expect($user->cloudContactLimit())->toBe(10);
});

test('a tier 1 special coupon adds its contacts on top of the package allowance', function () {
    $user = tierUser(2);
    $coupon = specialCouponFor(2);

    app(CloudActivationService::class)->activateStorage($user, $coupon->code);
    $user->refresh();

    // Royal base 10 + special coupon 7.
    expect($user->cloudContactLimit())->toBe(17);
});
