<?php

use App\Enums\CouponType;
use App\Models\AdditionalMinutePlan;
use App\Models\Coupon;
use App\Models\CouponBatch;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

test('coupon verification requires a code', function () {
    $this->getJson('/api/v1/coupons/verify')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('coupon verification rejects a code that is too short', function () {
    $this->getJson('/api/v1/coupons/verify?code=AB')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('unknown coupon codes return a not-found envelope', function () {
    $this->getJson('/api/v1/coupons/verify?code=NOPE123')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Coupon code was not found.')
        ->assertJsonPath('data', null);
});

test('a valid coupon is returned as a public object', function () {
    $agent = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Agent',
        'username' => 'johnagent',
        'email' => 'agent-secret@example.test',
        'is_agent' => true,
    ]);
    $package = Package::factory()->create([
        'name' => 'Premium',
        'description' => 'Premium subscription',
        'price' => 10,
        'daily_call_minutes' => 60,
    ]);
    $batch = tap(new CouponBatch, function (CouponBatch $batch) use ($agent, $package): void {
        $batch->forceFill([
            'agent_id' => $agent->id,
            'package_id' => $package->id,
            'type' => CouponType::ACTIVATION,
            'region' => 'nigeria',
            'quantity' => 1,
        ])->save();
    })->refresh();
    $coupon = Coupon::factory()->create([
        'code' => 'ABC123XYZ',
        'type' => CouponType::ACTIVATION,
        'package_id' => $package->id,
        'batch_id' => $batch->id,
        'agent_id' => $agent->id,
        'price' => 10,
        'region' => 'nigeria',
        'is_special' => false,
    ]);

    $response = $this->getJson('/api/v1/coupons/verify?code=abc123xyz')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Coupon code found.')
        ->assertJsonPath('data.code', 'ABC123XYZ')
        ->assertJsonPath('data.type', 'activation')
        ->assertJsonPath('data.type_name', 'Activation')
        ->assertJsonPath('data.is_valid', true)
        ->assertJsonPath('data.status', 'Available')
        ->assertJsonPath('data.used_at', null)
        ->assertJsonPath('data.display_name', 'Premium')
        ->assertJsonPath('data.package.id', $package->id)
        ->assertJsonPath('data.package.name', 'Premium')
        ->assertJsonPath('data.package.minutes', 60)
        ->assertJsonPath('data.additional_minute_plan', null)
        ->assertJsonPath('data.cloud_storage_plan', null)
        ->assertJsonPath('data.batch.reference', $batch->reference)
        ->assertJsonPath('data.batch.display_name', 'Premium')
        ->assertJsonPath('data.agent.id', $agent->id)
        ->assertJsonPath('data.agent.name', 'John Agent')
        ->assertJsonPath('data.agent.username', 'johnagent')
        ->assertJsonPath('data.verification.is_valid', true)
        ->assertJsonPath('data.verification.status', 'Available')
        ->assertJsonPath('data.verification.message', 'This coupon is valid and has not been redeemed.');

    expect($response->json('data'))->toBeArray()
        ->and($response->json('data'))->not->toHaveKey('redeemer')
        ->and($response->json('data.agent'))->not->toHaveKey('email')
        ->and($response->json('data.agent'))->not->toHaveKey('phone')
        ->and($response->json('data.price'))->toBeString();

    expect($coupon->code)->toBe('ABC123XYZ');
});

test('a redeemed coupon reports used status and public redeemer details', function () {
    $redeemer = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Okeke',
        'username' => 'adaokeke',
        'email' => 'ada-secret@example.test',
    ]);
    $coupon = Coupon::factory()->used()->create([
        'code' => 'USED99TOKEN',
        'user_id' => $redeemer->id,
        'price' => 5,
    ]);

    $response = $this->getJson('/api/v1/coupons/verify?code=USED99TOKEN')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $coupon->id)
        ->assertJsonPath('data.is_valid', false)
        ->assertJsonPath('data.status', 'Used')
        ->assertJsonPath('data.verification.is_valid', false)
        ->assertJsonPath('data.verification.status', 'Used')
        ->assertJsonPath('data.verification.message', 'This coupon has already been redeemed.')
        ->assertJsonPath('data.redeemer.name', 'Ada Okeke')
        ->assertJsonPath('data.redeemer.username', 'adaokeke');

    expect($response->json('data.used_at'))->not->toBeNull()
        ->and($response->json('data.redeemer'))->not->toHaveKey('id')
        ->and($response->json('data.redeemer'))->not->toHaveKey('email');
});

test('extra-minutes coupons include the minute plan product', function () {
    $plan = AdditionalMinutePlan::factory()->create([
        'name' => 'Boost 15',
        'price' => 5,
        'daily_call_minutes' => 15,
        'duration_days' => 30,
    ]);
    Coupon::factory()->create([
        'code' => 'MINS15CODE',
        'type' => CouponType::EXTRA_MINUTES,
        'additional_minute_plan_id' => $plan->id,
        'package_id' => null,
        'price' => 5,
    ]);

    $this->getJson('/api/v1/coupons/verify?code=MINS15CODE')
        ->assertOk()
        ->assertJsonPath('data.type', 'extra_minutes')
        ->assertJsonPath('data.display_name', 'Boost 15')
        ->assertJsonPath('data.package', null)
        ->assertJsonPath('data.additional_minute_plan.id', $plan->id)
        ->assertJsonPath('data.additional_minute_plan.name', 'Boost 15')
        ->assertJsonPath('data.additional_minute_plan.duration', 30)
        ->assertJsonPath('data.additional_minute_plan.minutes', 15);
});

test('validation failures do not consume the per-minute coupon check', function () {
    Coupon::factory()->create(['code' => 'VALIDCODE1']);

    $this->getJson('/api/v1/coupons/verify')->assertStatus(422);
    $this->getJson('/api/v1/coupons/verify?code=AB')->assertStatus(422);

    $this->getJson('/api/v1/coupons/verify?code=VALIDCODE1')
        ->assertOk()
        ->assertJsonPath('data.code', 'VALIDCODE1');
});

test('a client may only verify one coupon code per minute', function () {
    Coupon::factory()->create(['code' => 'FIRSTCODE99']);
    Coupon::factory()->create(['code' => 'SECONDCODE8']);

    $this->getJson('/api/v1/coupons/verify?code=FIRSTCODE99')
        ->assertOk()
        ->assertJsonPath('data.code', 'FIRSTCODE99');

    $this->getJson('/api/v1/coupons/verify?code=SECONDCODE8')
        ->assertStatus(429)
        ->assertJsonPath('success', false)
        ->assertHeader('Retry-After');

    $this->travel(61)->seconds();

    $this->getJson('/api/v1/coupons/verify?code=SECONDCODE8')
        ->assertOk()
        ->assertJsonPath('data.code', 'SECONDCODE8');
});

test('three unknown codes lock the client out for an hour', function () {
    Coupon::factory()->create(['code' => 'REALCODE99']);

    foreach (['NOPE111AAA', 'NOPE222BBB', 'NOPE333CCC'] as $code) {
        $this->getJson('/api/v1/coupons/verify?code='.$code)
            ->assertNotFound();

        $this->travel(61)->seconds();
    }

    $this->getJson('/api/v1/coupons/verify?code=REALCODE99')
        ->assertStatus(429)
        ->assertJsonPath('success', false)
        ->assertHeader('Retry-After');

    expect($this->getJson('/api/v1/coupons/verify?code=REALCODE99')->json('message'))
        ->toContain('invalid coupon codes');

    $this->travel(3600)->seconds();

    $this->getJson('/api/v1/coupons/verify?code=REALCODE99')
        ->assertOk()
        ->assertJsonPath('data.code', 'REALCODE99');
});

test('a found coupon does not count toward the unknown-code lockout', function () {
    Coupon::factory()->create(['code' => 'GOODCODE01']);

    $this->getJson('/api/v1/coupons/verify?code=NOPE111AAA')->assertNotFound();
    $this->travel(61)->seconds();
    $this->getJson('/api/v1/coupons/verify?code=NOPE222BBB')->assertNotFound();
    $this->travel(61)->seconds();
    $this->getJson('/api/v1/coupons/verify?code=GOODCODE01')->assertOk();
    $this->travel(61)->seconds();
    $this->getJson('/api/v1/coupons/verify?code=NOPE333CCC')->assertNotFound();
    $this->travel(61)->seconds();

    $this->getJson('/api/v1/coupons/verify?code=GOODCODE01')
        ->assertOk()
        ->assertJsonPath('data.code', 'GOODCODE01');
});
