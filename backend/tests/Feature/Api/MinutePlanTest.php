<?php

use App\Enums\CouponType;
use App\Models\AdditionalMinutePlan;
use App\Models\Coupon;

test('minute plans list pricing plus the user\'s current minutes', function () {
    $user = activatedUser();
    AdditionalMinutePlan::factory()->count(2)->create();
    AdditionalMinutePlan::factory()->create(['is_active' => false]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/minute-plans')
        ->assertOk();

    expect(count($response->json('data.plans')))->toBe(2)
        ->and($response->json('data.base_minutes'))->toEqual(60.0)
        ->and($response->json('data.bonus_minutes'))->toEqual(0.0);
});

test('an extra-minutes token activates a plan', function () {
    config(['settings.minutes.enabled' => true]);

    $user = activatedUser();
    $plan = AdditionalMinutePlan::factory()->create(['daily_call_minutes' => 15]);
    $coupon = Coupon::factory()->create([
        'type' => CouponType::EXTRA_MINUTES,
        'region' => 'nigeria',
        'additional_minute_plan_id' => $plan->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/minutes/redeem', ['plan_id' => $plan->id, 'token' => $coupon->code])
        ->assertOk()
        ->assertJsonPath('data.top_up.daily_call_minutes', 15);

    expect($user->refresh()->minuteTopUps()->count())->toBe(1)
        ->and($coupon->refresh()->used_at)->not->toBeNull();
});

test('a token for a different plan is rejected', function () {
    config(['settings.minutes.enabled' => true]);

    $user = activatedUser();
    $plan = AdditionalMinutePlan::factory()->create();
    $otherPlan = AdditionalMinutePlan::factory()->create();
    $coupon = Coupon::factory()->create([
        'type' => CouponType::EXTRA_MINUTES,
        'region' => 'nigeria',
        'additional_minute_plan_id' => $otherPlan->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/minutes/redeem', ['plan_id' => $plan->id, 'token' => $coupon->code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);

    expect($user->minuteTopUps()->count())->toBe(0);
});

test('used tokens cannot be redeemed twice', function () {
    config(['settings.minutes.enabled' => true]);

    $user = activatedUser();
    $plan = AdditionalMinutePlan::factory()->create();
    $coupon = Coupon::factory()->used()->create([
        'type' => CouponType::EXTRA_MINUTES,
        'region' => 'nigeria',
        'additional_minute_plan_id' => $plan->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/minutes/redeem', ['plan_id' => $plan->id, 'token' => $coupon->code])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

test('redemption is blocked while minutes are disabled', function () {
    config(['settings.minutes.enabled' => false]);

    $user = activatedUser();
    $plan = AdditionalMinutePlan::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/minutes/redeem', ['plan_id' => $plan->id, 'token' => 'ABCDEF1234'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_id']);
});
