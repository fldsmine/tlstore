<?php

use App\Enums\CallType as CallTypeEnum;
use App\Models\AllocatedNumber;
use App\Models\CallType;
use App\Models\Currency;

/** Attach a call type to the user's package so it counts as "in package". */
function attachType(?\App\Models\User $user, CallType $type): void
{
    $user->package->callTypes()->attach($type->id, [
        'is_available' => true,
        'rate_per_minute' => $type->rate_per_minute,
        'claimable_percentage' => $type->claimable_percentage,
    ]);
}

// ── Beneficiaries (allocated numbers) ───────────────────────────────────────

test('users list their own allocated numbers', function () {
    $user = activatedUser();
    AllocatedNumber::factory()->count(2)->create(['user_id' => $user->id, 'source' => 'package']);
    AllocatedNumber::factory()->create(['user_id' => apiUser()->id]); // someone else's

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/numbers')
        ->assertOk();

    expect(count($response->json('data.numbers')))->toBe(2);

    foreach ($response->json('data.numbers') as $row) {
        expect($row['number'])->toBeString()
            ->and($row['source'])->toBe('package')
            ->and(array_key_exists('is_expired', $row))->toBeTrue();
    }
});

test('expired numbers are flagged', function () {
    $user = activatedUser();
    AllocatedNumber::factory()->create([
        'user_id' => $user->id,
        'source' => 'additional',
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/numbers')
        ->assertOk()
        ->assertJsonPath('data.numbers.0.is_expired', true);
});

test('numbers require authentication', function () {
    $this->getJson('/api/v1/numbers')->assertStatus(401);
});

// ── Shareholder portal ──────────────────────────────────────────────────────

test('the portal breaks today\'s pool down per call type', function () {
    $user = shareEntitledUser();
    $onNet = CallType::factory()->create(['key' => CallTypeEnum::ON_NET]);
    $offNet = CallType::factory()->create(['key' => CallTypeEnum::OFF_NET]);
    attachType($user, $onNet); // off-net stays out of package

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/earnings/portal')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['cards', 'record_source', 'usage_verified', 'total_claimable_usd', 'total_claimed_usd', 'daily_allowance_minutes', 'remaining_minutes', 'package_name'],
        ]);

    $cards = collect($response->json('data.cards'));

    expect($cards->count())->toBeGreaterThanOrEqual(2);

    $inPackage = $cards->firstWhere('call_type_id', $onNet->id);
    $outOfPackage = $cards->firstWhere('call_type_id', $offNet->id);

    expect($inPackage['in_package'])->toBeTrue()
        ->and($outOfPackage['in_package'])->toBeFalse()
        ->and($outOfPackage['claimable_usd'])->toEqual(0.0)
        ->and($outOfPackage['unclaimed_calls'])->toBe(0);
});

test('the portal requires authentication', function () {
    $this->getJson('/api/v1/earnings/portal')->assertStatus(401);
});

// ── Display currency (profile) ──────────────────────────────────────────────

test('users can switch their display currency and back to USD', function () {
    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);
    $user = apiUser(['country' => 'NG']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [
            'first_name' => $user->first_name,
            'display_currency' => 'NGN',
        ])
        ->assertOk();

    expect($user->refresh()->display_currency)->toBe('NGN');

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [
            'first_name' => $user->first_name,
            'display_currency' => 'USD',
        ])
        ->assertOk();

    expect($user->refresh()->display_currency)->toBeNull();
});

test('an unknown display currency is rejected', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [
            'first_name' => $user->first_name,
            'display_currency' => 'XXX',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['display_currency']);
});

test('omitting display_currency keeps the existing preference', function () {
    Currency::create(['code' => 'GHS', 'name' => 'Ghanaian Cedi', 'symbol' => '₵', 'usd_exchange_rate' => 12, 'is_active' => true]);
    $user = apiUser(['display_currency' => 'GHS']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['first_name' => 'Renamed'])
        ->assertOk();

    expect($user->refresh()->display_currency)->toBe('GHS')
        ->and($user->first_name)->toBe('Renamed');
});
