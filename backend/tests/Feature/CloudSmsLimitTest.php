<?php

use App\Enums\CouponType;
use App\Models\CloudInboxMessage;
use App\Models\CloudStoragePlan;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\User;
use App\Services\CloudActivationService;
use App\Services\CloudInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Diamond (tier 1): SMS inbox with a base daily SMS limit of 10, no calling.
    Package::create([
        'name' => 'Diamond eSIM',
        'tier_level' => 1,
        'enable_cloud' => true,
        'cloud_calls' => false,
        'cloud_sms' => true,
        'cloud_contact_limit' => 0,
        'cloud_sms_limit' => 10,
    ]);

    // Royal (tier 2): full cloud, base daily SMS limit of 20.
    Package::create([
        'name' => 'Royal eSIM',
        'tier_level' => 2,
        'enable_cloud' => true,
        'cloud_calls' => true,
        'cloud_sms' => true,
        'cloud_contact_limit' => 10,
        'cloud_sms_limit' => 20,
    ]);

    // Regular purchasable pack: +5 contacts and +5 SMS/day.
    CloudStoragePlan::create([
        'name' => 'Starter Storage',
        'extra_contacts' => 5,
        'extra_sms' => 5,
        'tier_level' => null,
        'is_active' => true,
        'duration_days' => 0,
    ]);

    // Special coupon pack (tier 1): +4 contacts and +4 SMS/day.
    CloudStoragePlan::create([
        'name' => 'Tier 1 Special Storage',
        'extra_contacts' => 4,
        'extra_sms' => 4,
        'tier_level' => 1,
        'is_active' => true,
    ]);
});

function smsUser(int $tier): User
{
    return User::factory()->create([
        'package_id' => Package::where('tier_level', $tier)->first()->id,
    ]);
}

function sponsoredMessage(User $user, float $rate = 0.5): CloudInboxMessage
{
    return CloudInboxMessage::create([
        'user_id' => $user->id,
        'type' => CloudInboxMessage::TYPE_SPONSORED,
        'sender_name' => 'Sponsor',
        'sender_number' => '+16980000001',
        'subject' => 'Offer',
        'body' => 'Read me and claim.',
        'earn_rate' => $rate,
    ]);
}

test('the daily SMS limit comes from the package base quota', function () {
    $user = smsUser(1);

    expect($user->cloudSmsLimit())->toBe(10);
    expect(smsUser(2)->cloudSmsLimit())->toBe(20);
});

test('claims are blocked once the daily SMS limit is reached', function () {
    $user = smsUser(1);
    $service = app(CloudInboxService::class);

    $claimed = 0;
    foreach (range(1, 12) as $i) {
        if ($service->claim($user, sponsoredMessage($user)) > 0) {
            $claimed++;
        }
    }

    // 10 of 12 messages paid out; the last two were refused by the limit.
    expect($claimed)->toBe(10);
    expect($service->smsClaimedToday($user))->toBe(10);
    expect($service->smsLimitReached($user))->toBeTrue();
    expect((float) $user->refresh()->cloud_sms_balance)->toBe(5.0);
});

test('the SMS limit resets each day', function () {
    $user = smsUser(1);
    $service = app(CloudInboxService::class);

    // A message claimed yesterday must not count against today's limit.
    $yesterday = sponsoredMessage($user);
    $yesterday->forceFill(['claimed_at' => now()->subDay()])->save();

    expect($service->smsClaimedToday($user))->toBe(0);

    $claimed = 0;
    foreach (range(1, 10) as $i) {
        if ($service->claim($user, sponsoredMessage($user)) > 0) {
            $claimed++;
        }
    }

    expect($claimed)->toBe(10);
    expect($service->smsLimitReached($user))->toBeTrue();
});

test('a package with no base SMS limit stays unlimited', function () {
    Package::create([
        'name' => 'Unlimited SMS eSIM',
        'tier_level' => 3,
        'enable_cloud' => true,
        'cloud_calls' => false,
        'cloud_sms' => true,
        'cloud_contact_limit' => 0,
        'cloud_sms_limit' => 0,
    ]);

    $user = User::factory()->create(['package_id' => Package::where('tier_level', 3)->first()->id]);
    $service = app(CloudInboxService::class);

    expect($user->cloudSmsLimit())->toBe(0);

    $claimed = 0;
    foreach (range(1, 15) as $i) {
        if ($service->claim($user, sponsoredMessage($user)) > 0) {
            $claimed++;
        }
    }

    expect($claimed)->toBe(15);
    expect($service->smsLimitReached($user))->toBeFalse();
});

test('a purchased storage pack adds its extra_sms to the daily limit', function () {
    $user = smsUser(2); // Royal, base 20
    $plan = CloudStoragePlan::whereNull('tier_level')->first();

    $coupon = Coupon::create([
        'code' => 'CLOUDPACK',
        'code_hash' => Coupon::hashCode('CLOUDPACK'),
        'type' => CouponType::CLOUD,
        'cloud_storage_plan_id' => $plan->id,
    ]);

    app(CloudActivationService::class)->redeem($user, $coupon->code, 'redeemCode', $plan->id);

    // 20 base + 5 extra from the pack.
    expect($user->refresh()->cloudSmsLimit())->toBe(25);

    // The redeemed top-up snapshots the pack's extra_sms.
    $topUp = $user->cloudStorageTopUps()->first();
    expect((int) $topUp->extra_sms)->toBe(5);
});

test('a special coupon adds its extra_sms to the daily limit', function () {
    $user = smsUser(1); // Diamond, base 10
    $plan = CloudStoragePlan::where('tier_level', 1)->first();

    $coupon = Coupon::create([
        'code' => 'SPECIALONE',
        'code_hash' => Coupon::hashCode('SPECIALONE'),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $plan->id,
        'expires_at' => now()->addDays(90),
    ]);

    app(CloudActivationService::class)->activateStorage($user, $coupon->code);

    // 10 base + 4 extra from the special coupon pack.
    expect($user->refresh()->cloudSmsLimit())->toBe(14);

    $topUp = $user->cloudStorageTopUps()->first();
    expect((int) $topUp->extra_sms)->toBe(4);
});

test('an expired storage pack no longer adds extra SMS', function () {
    $user = smsUser(2);
    $plan = CloudStoragePlan::whereNull('tier_level')->first();

    $coupon = Coupon::create([
        'code' => 'CLOUDPACK',
        'code_hash' => Coupon::hashCode('CLOUDPACK'),
        'type' => CouponType::CLOUD,
        'cloud_storage_plan_id' => $plan->id,
    ]);

    app(CloudActivationService::class)->redeem($user, $coupon->code, 'redeemCode', $plan->id);
    expect($user->refresh()->cloudSmsLimit())->toBe(25);

    $user->cloudStorageTopUps()->update(['expires_at' => now()->subMinute()]);

    expect($user->refresh()->cloudSmsLimit())->toBe(20);
});

test('users without the SMS capability have no SMS limit to claim against', function () {
    Package::create([
        'name' => 'No Cloud eSIM',
        'tier_level' => 4,
        'enable_cloud' => false,
        'cloud_calls' => false,
        'cloud_sms' => false,
        'cloud_contact_limit' => 0,
        'cloud_sms_limit' => 10,
    ]);

    $user = User::factory()->create(['package_id' => Package::where('tier_level', 4)->first()->id]);
    $service = app(CloudInboxService::class);

    expect($user->cloudSmsLimit())->toBe(0);
    expect($service->claim($user, sponsoredMessage($user)))->toBe(0.0);
    expect((float) $user->refresh()->cloud_sms_balance)->toBe(0.0);
});

test('already-claimed messages still pay nothing when under the limit', function () {
    $user = smsUser(1);
    $service = app(CloudInboxService::class);

    $message = sponsoredMessage($user);

    expect($service->claim($user, $message))->toBe(0.5);
    expect($service->claim($user, $message))->toBe(0.0);
    expect($service->smsClaimedToday($user))->toBe(1);
});
