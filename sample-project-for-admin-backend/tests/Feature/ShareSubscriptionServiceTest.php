<?php

use App\Models\AdditionalMinutePlan;
use App\Models\CallClaim;
use App\Models\CallRecord;
use App\Models\CallType;
use App\Models\MinuteTopUp;
use App\Models\Package;
use App\Models\ShareSubscription;
use App\Models\User;
use App\Livewire\Dashboard\Earnings\Earnings;
use App\Services\ClaimService;
use App\Services\PackageActivationService;
use App\Services\ShareSubscriptionService;
use Illuminate\Validation\ValidationException;

test('activating a configured package snapshots a dated share-term entitlement', function () {
    $package = Package::factory()->create([
        'monthly_share_minutes' => 600,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 25,
        'call_id_limit' => 0,
    ]);
    $user = User::factory()->create(['package_id' => null]);

    app(PackageActivationService::class)->activate($user, $package);

    $subscription = ShareSubscription::query()->where('user_id', $user->id)->sole();

    expect($subscription->package_id)->toBe($package->id)
        ->and((float) $subscription->allocated_minutes)->toBe(600.0)
        ->and((float) $subscription->daily_earning_cap)->toBe(25.0)
        ->and($subscription->starts_at->isToday())->toBeTrue()
        ->and($subscription->expires_at->equalTo($subscription->starts_at->copy()->addDays(30)))->toBeTrue()
        ->and($subscription->status)->toBe('active');
});

test('monthly share minutes are prorated into the full subscription-term allocation', function () {
    $package = Package::factory()->create([
        'monthly_share_minutes' => 600,
        'share_term_days' => 45,
        'max_daily_shareholder_earning' => 25,
        'call_id_limit' => 0,
    ]);
    $user = User::factory()->create(['package_id' => null]);

    app(PackageActivationService::class)->activate($user, $package);

    $subscription = ShareSubscription::query()->where('user_id', $user->id)->sole();

    expect((float) $subscription->allocated_minutes)->toBe(900.0);
});

test('standalone CallType share quotas are prorated across their configured term', function () {
    $callType = CallType::factory()->create([
        'monthly_share_minutes' => 600,
        'share_term_days' => 45,
        'max_daily_shareholder_earning' => 25,
    ]);
    $user = User::factory()->create(['package_id' => null]);

    $subscription = app(ShareSubscriptionService::class)->startForCallType($user, $callType);

    expect((float) $subscription->allocated_minutes)->toBe(900.0);
});

test('base share minutes accrue daily at claim time and unused accrued minutes carry forward', function () {
    $package = Package::factory()->create([
        'monthly_share_minutes' => 600,
        'share_term_days' => 90,
        'max_daily_shareholder_earning' => 100,
        'call_id_limit' => 0,
    ]);
    $user = User::factory()->create(['package_id' => null]);
    app(PackageActivationService::class)->activate($user, $package);
    $user->refresh();

    $callType = CallType::factory()->create(['claimable_percentage' => 100]);
    $package->callTypes()->attach($callType->id, [
        'is_available' => true,
        'rate_per_minute' => $callType->rate_per_minute,
        'claimable_percentage' => 100,
    ]);
    $tooLong = CallRecord::factory()->create([
        'call_type_id' => $callType->id,
        'duration_minutes' => 25,
        'earnings' => 5,
    ]);
    $shortCall = CallRecord::factory()->create([
        'call_type_id' => $callType->id,
        'duration_minutes' => 5,
        'earnings' => 5,
    ]);

    $claims = app(ClaimService::class);
    $subscription = app(ShareSubscriptionService::class)->current($user);

    expect((float) $subscription->allocated_minutes)->toBe(1800.0)
        ->and($claims->remainingMinutes($user, $subscription))->toBe(1800.0)
        ->and($claims->availableMinutes($user, $subscription))->toBe(20.0)
        ->and($claims->blockedReason($user, $tooLong))->toBeNull()
        ->and($claims->claimableMinutes($user, $tooLong, $subscription))->toBe(20.0)
        ->and($claims->claim($user, $shortCall))->toBe(5.0)
        ->and($claims->availableMinutes($user, $subscription))->toBe(15.0)
        ->and($claims->claimableMinutes($user, $tooLong, $subscription))->toBe(15.0)
        ->and($claims->claim($user, $tooLong))->toBe(3.0)
        ->and((float) CallClaim::query()->where('call_record_id', $tooLong->id)->value('duration_minutes'))->toBe(15.0)
        ->and($claims->remainingMinutes($user, $subscription))->toBe(1780.0)
        ->and($claims->availableMinutes($user, $subscription))->toBe(0.0);

    $this->travel(1)->days();

    expect($claims->availableMinutes($user, $subscription))->toBe(20.0);
});

test('claimable summaries allocate shared minute and daily limits across rows only once', function () {
    $package = Package::factory()->create([
        'monthly_share_minutes' => 600,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 0.1,
        'call_id_limit' => 0,
    ]);
    $user = User::factory()->create(['package_id' => null]);
    app(PackageActivationService::class)->activate($user, $package);
    $user->refresh();

    $callType = CallType::factory()->create(['claimable_percentage' => 100]);
    $package->callTypes()->attach($callType->id, [
        'is_available' => true,
        'rate_per_minute' => $callType->rate_per_minute,
        'claimable_percentage' => 100,
    ]);
    $records = collect([
        CallRecord::factory()->create(['call_type_id' => $callType->id, 'duration_minutes' => 10, 'earnings' => 1]),
        CallRecord::factory()->create(['call_type_id' => $callType->id, 'duration_minutes' => 10, 'earnings' => 1]),
    ]);
    $subscription = app(ShareSubscriptionService::class)->current($user);

    $breakdown = app(ClaimService::class)->claimableBreakdown($user, $records, $subscription, 20, 0.1);

    expect($breakdown)->toHaveCount(1)
        ->and($breakdown[0]['minutes'])->toBe(1.0)
        ->and($breakdown[0]['amount'])->toBe(0.1)
        ->and(array_sum(array_column($breakdown, 'amount')))->toBe(0.1);
});

test('an upgrade starts a fresh allocation and does not carry previous-term top-ups', function () {
    $base = Package::factory()->create([
        'tier_level' => 1,
        'monthly_share_minutes' => 100,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 10,
        'call_id_limit' => 0,
    ]);
    $upgrade = Package::factory()->create([
        'tier_level' => 2,
        'monthly_share_minutes' => 300,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 20,
        'call_id_limit' => 0,
    ]);
    $user = User::factory()->create(['package_id' => null]);
    $activation = app(PackageActivationService::class);
    $activation->activate($user, $base);
    $user->refresh();

    $oldSubscription = app(ShareSubscriptionService::class)->current($user);
    $oldTopUp = MinuteTopUp::factory()->create([
        'user_id' => $user->id,
        'share_subscription_id' => $oldSubscription->id,
        'share_minutes' => 75,
        'expires_at' => now()->addDays(10),
        'share_expires_at' => $oldSubscription->expires_at,
    ]);

    expect(app(ClaimService::class)->allocatedMinutes($user, $oldSubscription))->toBe(175.0);

    $activation->upgrade($user, $upgrade);
    $user->refresh();
    $newSubscription = app(ShareSubscriptionService::class)->current($user);

    expect($newSubscription)->not->toBeNull()
        ->and($newSubscription->id)->not->toBe($oldSubscription->id)
        ->and((float) $newSubscription->allocated_minutes)->toBe(300.0)
        ->and(app(ClaimService::class)->allocatedMinutes($user, $newSubscription))->toBe(300.0)
        ->and($oldSubscription->refresh()->status)->toBe('superseded')
        ->and($newSubscription->minuteTopUps()->count())->toBe(0)
        ->and($oldTopUp->share_expires_at->isFuture())->toBeTrue()
        ->and($oldSubscription->minuteTopUps()->shareActive()->count())->toBe(0);
});

test('share-minute top-ups add to the current term while eSIM daily minutes stay separate', function () {
    $package = Package::factory()->create([
        'monthly_share_minutes' => 100,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 10,
        'daily_call_minutes' => 60,
        'call_id_limit' => 0,
    ]);
    $user = User::factory()->create(['package_id' => null]);
    app(PackageActivationService::class)->activate($user, $package);
    $user->refresh();

    $plan = AdditionalMinutePlan::factory()->create([
        'daily_call_minutes' => 20,
        'share_minutes' => 50,
        'duration_days' => 7,
    ]);
    $topUp = app(PackageActivationService::class)->addMinutes($user, $plan);
    $subscription = app(ShareSubscriptionService::class)->current($user);

    expect($topUp->share_subscription_id)->toBe($subscription->id)
        ->and($topUp->expires_at->lessThan($subscription->expires_at))->toBeTrue()
        ->and($topUp->share_expires_at->equalTo($topUp->expires_at))->toBeTrue()
        ->and(app(ClaimService::class)->allocatedMinutes($user, $subscription))->toBe(150.0)
        ->and(app(ClaimService::class)->dailyAllowance($user))->toBe(80.0);

    $this->travel(8)->days();
    $topUp->refresh();

    expect($topUp->expires_at->isPast())->toBeTrue()
        ->and($topUp->share_expires_at->isPast())->toBeTrue()
        ->and(app(ClaimService::class)->allocatedMinutes($user, $subscription))->toBe(100.0)
        ->and(app(ClaimService::class)->dailyAllowance($user))->toBe(60.0);
});

test('usage attributed to an expired share top-up does not reduce base or other active quota', function () {
    $package = Package::factory()->create([
        'monthly_share_minutes' => 100,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 100,
        'call_id_limit' => 0,
    ]);
    $user = User::factory()->create(['package_id' => null]);
    app(PackageActivationService::class)->activate($user, $package);
    $user->refresh();

    $shortPlan = AdditionalMinutePlan::factory()->create([
        'daily_call_minutes' => 0,
        'share_minutes' => 10,
        'duration_days' => 7,
    ]);
    $longPlan = AdditionalMinutePlan::factory()->create([
        'daily_call_minutes' => 0,
        'share_minutes' => 100,
        'duration_days' => 30,
    ]);
    $shortTopUp = app(PackageActivationService::class)->addMinutes($user, $shortPlan);
    $longTopUp = app(PackageActivationService::class)->addMinutes($user, $longPlan);

    $callType = CallType::factory()->create(['claimable_percentage' => 100]);
    $package->callTypes()->attach($callType->id, [
        'is_available' => true,
        'rate_per_minute' => $callType->rate_per_minute,
        'claimable_percentage' => 100,
    ]);
    $baseRecord = CallRecord::factory()->create([
        'call_type_id' => $callType->id,
        'duration_minutes' => 100,
        'earnings' => 1,
    ]);
    $shortTopUpRecord = CallRecord::factory()->create([
        'call_type_id' => $callType->id,
        'duration_minutes' => 5,
        'earnings' => 1,
    ]);

    $claims = app(ClaimService::class);
    expect($claims->claim($user, $baseRecord))->toBe(1.0)
        ->and($claims->claim($user, $shortTopUpRecord))->toBe(1.0)
        ->and($baseRecord->claims()->firstOrFail()->minute_top_up_id)->toBe($shortTopUp->id)
        ->and($shortTopUpRecord->claims()->firstOrFail()->minute_top_up_id)->toBe($longTopUp->id);

    $subscription = app(ShareSubscriptionService::class)->current($user);
    expect($claims->remainingMinutes($user, $subscription))->toBe(105.0);

    $this->travel(8)->days();

    expect($shortTopUp->refresh()->share_expires_at->isPast())->toBeTrue()
        ->and($longTopUp->refresh()->share_expires_at->isFuture())->toBeTrue()
        ->and($claims->allocatedMinutes($user, $subscription))->toBe(200.0)
        ->and($claims->remainingMinutes($user, $subscription))->toBe(105.0);
});

test('a share-minute top-up cannot attach without a current share term', function () {
    $user = activatedUser();
    $plan = AdditionalMinutePlan::factory()->create(['share_minutes' => 25]);

    expect(fn () => app(PackageActivationService::class)->addMinutes($user, $plan))
        ->toThrow(ValidationException::class);

    expect($user->minuteTopUps()->count())->toBe(0);
});

test('expired share terms can renew only into a fresh allocation', function () {
    $package = Package::factory()->create([
        'monthly_share_minutes' => 240,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 15,
    ]);
    $user = User::factory()->create(['package_id' => $package->id]);
    $old = ShareSubscription::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'allocated_minutes' => 60,
        'daily_earning_cap' => 5,
        'starts_at' => now()->subDays(31),
        'expires_at' => now()->subDay(),
        'status' => 'active',
    ]);

    $renewed = app(ShareSubscriptionService::class)->renew($user, $package);

    expect($renewed->id)->not->toBe($old->id)
        ->and((float) $renewed->allocated_minutes)->toBe(240.0)
        ->and((float) $renewed->daily_earning_cap)->toBe(15.0)
        ->and($old->refresh()->status)->toBe('expired');
});

test('an active share term cannot be renewed early', function () {
    $user = shareEntitledUser();
    $package = $user->package;

    expect(fn () => app(ShareSubscriptionService::class)->renew($user, $package))
        ->toThrow(ValidationException::class);
});

test('an activation coupon starts the package share term through the existing activation flow', function () {
    $package = Package::factory()->create([
        'monthly_share_minutes' => 360,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 18,
        'call_id_limit' => 0,
    ]);
    $user = User::factory()->create(['package_id' => null, 'country' => 'NG']);
    $coupon = \App\Models\Coupon::factory()->create([
        'type' => \App\Enums\CouponType::ACTIVATION,
        'package_id' => $package->id,
        'region' => \App\Models\Coupon::REGION_NIGERIA,
    ]);

    app(\App\Services\TokenRedemptionService::class)->activate($user, $package->id, $coupon->code);

    $user->refresh();
    $subscription = app(ShareSubscriptionService::class)->current($user);

    expect($user->package_id)->toBe($package->id)
        ->and($subscription)->not->toBeNull()
        ->and((float) $subscription->allocated_minutes)->toBe(360.0)
        ->and((float) $subscription->daily_earning_cap)->toBe(18.0)
        ->and($coupon->refresh()->used_at)->not->toBeNull();
});

test('web claim-all includes eligible records beyond the currently visible page', function () {
    $user = shareEntitledUser();
    $callType = CallType::factory()->create(['claimable_percentage' => 100]);
    $user->package->callTypes()->attach($callType->id, [
        'is_available' => true,
        'rate_per_minute' => $callType->rate_per_minute,
        'claimable_percentage' => 100,
    ]);

    foreach (range(1, 13) as $index) {
        CallRecord::factory()->create([
            'call_type_id' => $callType->id,
            'duration_minutes' => 1,
            'earnings' => 0.1,
        ]);
    }

    $this->actingAs($user);
    \Livewire\Livewire::test(Earnings::class)
        ->assertSee('Claim all available')
        ->call('claimAll')
        ->assertDispatched('toast', type: 'success');

    expect(CallClaim::query()->where('user_id', $user->id)->count())->toBe(13)
        ->and((float) $user->refresh()->shareholder_balance)->toBe(1.3);
});
