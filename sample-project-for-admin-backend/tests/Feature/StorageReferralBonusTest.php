<?php

use App\Enums\CouponType;
use App\Enums\TransactionType;
use App\Models\CloudStoragePlan;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CloudActivationService;
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

    CloudStoragePlan::create([
        'name' => 'Tier 1 Special Storage',
        'extra_contacts' => 4,
        'extra_sms' => 4,
        'tier_level' => 1,
        'is_active' => true,
        'referral_bonus' => 0.25,
    ]);

    CloudStoragePlan::create([
        'name' => 'Starter Storage',
        'extra_contacts' => 5,
        'extra_sms' => 5,
        'is_active' => true,
        'referral_bonus' => 0.5,
    ]);
});

function buyerWithUpline(?int $uplinePackageTier = 2): array
{
    $upline = User::factory()->create([
        'package_id' => $uplinePackageTier === null ? null : Package::where('tier_level', $uplinePackageTier)->first()->id,
    ]);

    $buyer = User::factory()->create([
        'package_id' => Package::where('tier_level', 1)->first()->id,
        'referrer_id' => $upline->id,
    ]);

    return [$buyer, $upline];
}

function specialCoupon(string $code, ?int $tier = 1): Coupon
{
    $plan = CloudStoragePlan::where('tier_level', $tier)->first();

    return Coupon::create([
        'code' => $code,
        'code_hash' => Coupon::hashCode($code),
        'type' => CouponType::STORAGE_ACTIVATION,
        'cloud_storage_plan_id' => $plan->id,
        'expires_at' => now()->addDays(90),
    ]);
}

test('activating a special coupon pays the plan referral bonus to the upline', function () {
    [$buyer, $upline] = buyerWithUpline();
    $coupon = specialCoupon('SPECIALREF');

    app(CloudActivationService::class)->activateStorage($buyer, $coupon->code);

    // Upline (referral_bonus_balance) is credited the plan's referral_bonus.
    expect((float) $upline->refresh()->referral_bonus_balance)->toBe(0.25);

    $transaction = Transaction::where('user_id', $upline->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->type)->toBe(TransactionType::REFERRAL_BONUS);
    expect($transaction->wallet)->toBe('referral_bonus');
    expect((float) $transaction->amount)->toBe(0.25);
    expect($transaction->description)->toContain($buyer->username);
});

test('activating a special coupon pays nothing when the plan has no referral bonus', function () {
    [$buyer, $upline] = buyerWithUpline();

    // Zero the bonus out — the seeded special plans ship with referral_bonus = 0.
    CloudStoragePlan::where('tier_level', 1)->update(['referral_bonus' => 0]);

    $coupon = specialCoupon('SPECIALNOBONUS');

    app(CloudActivationService::class)->activateStorage($buyer, $coupon->code);

    expect((float) $upline->refresh()->referral_bonus_balance)->toBe(0.0);
    expect(Transaction::where('user_id', $upline->id)->count())->toBe(0);
});

test('activating a special coupon pays nothing when the buyer has no referrer', function () {
    $buyer = User::factory()->create([
        'package_id' => Package::where('tier_level', 1)->first()->id,
        'referrer_id' => null,
    ]);

    $coupon = specialCoupon('SPECIALNOREF');

    app(CloudActivationService::class)->activateStorage($buyer, $coupon->code);

    expect(Transaction::where('type', TransactionType::REFERRAL_BONUS)->count())->toBe(0);
});

test('activating a special coupon pays nothing when the upline has no package', function () {
    [$buyer, $upline] = buyerWithUpline(uplinePackageTier: null);
    $coupon = specialCoupon('SPECIALNOPKG');

    app(CloudActivationService::class)->activateStorage($buyer, $coupon->code);

    expect((float) $upline->refresh()->referral_bonus_balance)->toBe(0.0);
    expect(Transaction::where('user_id', $upline->id)->count())->toBe(0);
});

test('redeeming a purchased storage pack still pays the plan referral bonus', function () {
    $upline = User::factory()->create([
        'package_id' => Package::where('tier_level', 2)->first()->id,
    ]);

    $buyer = User::factory()->create([
        'package_id' => Package::where('tier_level', 2)->first()->id,
        'referrer_id' => $upline->id,
    ]);

    $plan = CloudStoragePlan::whereNull('tier_level')->first();

    $coupon = Coupon::create([
        'code' => 'CLOUDPACK',
        'code_hash' => Coupon::hashCode('CLOUDPACK'),
        'type' => CouponType::CLOUD,
        'cloud_storage_plan_id' => $plan->id,
    ]);

    app(CloudActivationService::class)->redeem($buyer, $coupon->code, 'redeemCode', $plan->id);

    expect((float) $upline->refresh()->referral_bonus_balance)->toBe(0.5);
    expect(Transaction::where('user_id', $upline->id)->where('type', TransactionType::REFERRAL_BONUS)->count())->toBe(1);
});

test('the special coupon page offers the reusable verify-code bottom sheet', function () {
    // The page sits behind the onboarding gate — the user needs a package and a PIN.
    $user = User::factory()->create([
        'package_id' => Package::where('tier_level', 1)->first()->id,
        'transaction_pin' => bcrypt('1234'),
    ]);

    $this->actingAs($user)
        ->get(route('special-coupon'))
        ->assertOk()
        ->assertSee('Verify a coupon code')
        ->assertSee('coupons/verify');
});

test('the verify sheet binds data-lock-scroll dynamically so the page scroll stays free while closed', function () {
    $user = User::factory()->create([
        'package_id' => Package::where('tier_level', 1)->first()->id,
        'transaction_pin' => bcrypt('1234'),
    ]);

    // The scroll-lock watcher locks the page whenever [data-lock-scroll] exists in the DOM —
    // the sheet must only carry the attribute while open, never as a static attribute.
    $this->actingAs($user)
        ->get(route('special-coupon'))
        ->assertOk()
        ->assertSee(':data-lock-scroll="open ?', false)
        ->assertDontSee('sm:px-4" data-lock-scroll', false);
});
