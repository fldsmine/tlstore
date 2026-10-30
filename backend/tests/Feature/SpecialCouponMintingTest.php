<?php

use App\Enums\CouponType;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CloudStoragePlan;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\User;
use App\Services\CouponMintingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
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

    // A regular purchasable storage plan (no tier).
    CloudStoragePlan::create([
        'name' => 'Starter Storage',
        'token_price' => 3,
        'agent_price' => 2.4,
        'extra_contacts' => 5,
        'referral_bonus' => 0,
        'duration_days' => 0,
        'sort_order' => 0,
        'tier_level' => null,
        'is_active' => true,
    ]);

    // Special coupon storage plans (tier-specific, coupon-only).
    CloudStoragePlan::create([
        'name' => 'Tier 1 Special Storage',
        'extra_contacts' => 4,
        'tier_level' => 1,
        'token_price' => 0,
        'agent_price' => 0,
        'referral_bonus' => 0,
        'duration_days' => 0,
        'sort_order' => 100,
        'is_active' => true,
    ]);

    CloudStoragePlan::create([
        'name' => 'Tier 2 Special Storage',
        'extra_contacts' => 7,
        'tier_level' => 2,
        'token_price' => 0,
        'agent_price' => 0,
        'referral_bonus' => 0,
        'duration_days' => 0,
        'sort_order' => 101,
        'is_active' => true,
    ]);
});

function vendorUser(float $agentBalance = 100.0): User
{
    return User::factory()->agent()->create([
        'agent_balance' => $agentBalance,
        'country' => 'NG',
    ]);
}

test('special plans are listed under a separate Special Coupons group', function () {
    $vendor = vendorUser();

    $options = app(CouponMintingService::class)->planOptions($vendor);

    $regular = $options->firstWhere('group', 'Cloud Storage Plans');
    $special = $options->where('group', 'Special Coupons');

    expect($regular)->not->toBeNull();
    expect($regular['special'])->toBeFalse();

    expect($special)->toHaveCount(2);
    expect($special->pluck('special')->unique()->values()->all())->toBe([true]);
    expect($special->pluck('tier_level')->sort()->values()->all())->toBe([1, 2]);
});

test('minting a special plan creates storage activation coupons with an expiry', function () {
    $vendor = vendorUser();
    $service = app(CouponMintingService::class);

    $specialPlan = CloudStoragePlan::where('tier_level', 2)->first();

    $result = $service->mint($vendor, 'cloud:'.$specialPlan->id, 3);

    $coupons = Coupon::where('batch_id', $result['batch']->id)->get();

    expect($coupons)->toHaveCount(3);

    foreach ($coupons as $coupon) {
        expect($coupon->type)->toBe(CouponType::STORAGE_ACTIVATION);
        expect($coupon->cloud_storage_plan_id)->toBe($specialPlan->id);
        expect($coupon->user_id)->toBeNull();
        expect($coupon->used_at)->toBeNull();
        expect($coupon->expires_at)->not->toBeNull();
        // 90-day expiry per settings.token.special_expiry_days.
        expect($coupon->expires_at->between(now()->addDays(89), now()->addDays(91)))->toBeTrue();
        // HMAC hash is stored for secure lookup.
        expect($coupon->code_hash)->toBe(Coupon::hashCode($coupon->code));
    }

    expect($result['batch']->type)->toBe(CouponType::STORAGE_ACTIVATION);
});

test('minting a regular cloud plan still creates plain cloud coupons without expiry', function () {
    $vendor = vendorUser();
    $service = app(CouponMintingService::class);

    $regularPlan = CloudStoragePlan::whereNull('tier_level')->first();

    $result = $service->mint($vendor, 'cloud:'.$regularPlan->id, 2);

    $coupons = Coupon::where('batch_id', $result['batch']->id)->get();

    expect($coupons)->toHaveCount(2);

    foreach ($coupons as $coupon) {
        expect($coupon->type)->toBe(CouponType::CLOUD);
        expect($coupon->expires_at)->toBeNull();
    }

    // Regular plans are charged at the agent price.
    expect((float) $result['cost'])->toBe(round(2.4 * 2, 2));
    expect((float) $vendor->refresh()->agent_balance)->toBe(100.0 - (float) $result['cost']);
});

test('special coupons minted by a vendor can be activated and then used for withdrawal', function () {
    $vendor = vendorUser();
    $service = app(CouponMintingService::class);

    $specialPlan = CloudStoragePlan::where('tier_level', 2)->first();
    $service->mint($vendor, 'cloud:'.$specialPlan->id, 1);

    $coupon = Coupon::where('type', CouponType::STORAGE_ACTIVATION)->first();

    // A tier-2 user activates storage with the code.
    $user = User::factory()->create([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'cloud_call_balance' => 50.0,
        'transaction_pin' => bcrypt('1234'),
        'country' => 'NG',
    ]);

    // Withdrawals need a payout bank account.
    Bank::create(['name' => 'Test Bank', 'code' => 'TESTBANK', 'tag' => 'test']);
    BankAccount::create([
        'user_id' => $user->id,
        'bank_id' => Bank::first()->id,
        'account_number' => '1234567890',
        'account_name' => 'Test Account',
    ]);

    $plan = app(\App\Services\CloudActivationService::class)->activateStorage($user, $coupon->code);

    expect($plan->id)->toBe($specialPlan->id);
    expect($user->cloudStorageTopUps()->count())->toBe(1);
    expect((int) $user->cloudStorageTopUps()->first()->extra_contacts)->toBe(7);

    // Coupon is now bound to the user but NOT consumed.
    $coupon->refresh();
    expect($coupon->user_id)->toBe($user->id);
    expect($coupon->used_at)->toBeNull();

    // Pre-validation passes for the bound user...
    app(\App\Services\WithdrawalService::class)->validateAuthorizationCode($user, $coupon->code);

    // ...and the withdrawal consumes it.
    $withdrawal = app(\App\Services\WithdrawalService::class)->request($user, 'cloud_call', 10.0, $coupon->code);
    expect($withdrawal)->not->toBeNull();
    expect($coupon->refresh()->used_at)->not->toBeNull();
});

test('special coupon expiry days are configurable', function () {
    config(['settings.token.special_expiry_days' => 30]);

    $vendor = vendorUser();
    $service = app(CouponMintingService::class);

    $specialPlan = CloudStoragePlan::where('tier_level', 1)->first();
    $service->mint($vendor, 'cloud:'.$specialPlan->id, 1);

    $coupon = Coupon::where('type', CouponType::STORAGE_ACTIVATION)->first();

    expect($coupon->expires_at->between(now()->addDays(29), now()->addDays(31)))->toBeTrue();
});
