<?php

use App\Enums\CallType as CallTypeEnum;
use App\Enums\TransactionType;
use App\Models\CallClaim;
use App\Models\CallRecord;
use App\Models\CallType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ClaimService;
use Illuminate\Support\Facades\DB;

/** Attach a call type to the user's package so it counts as "in package". */
function attachToPackage(User $user, CallType $type): void
{
    $user->package->callTypes()->attach($type->id, [
        'is_available' => true,
        'rate_per_minute' => $type->rate_per_minute,
        'claimable_percentage' => $type->claimable_percentage,
    ]);
}

test('earnings lists today\'s in-package call pool with separate eSIM and share-term allowances', function () {
    $user = shareEntitledUser();
    $onNet = CallType::factory()->create(['key' => CallTypeEnum::ON_NET]);
    $offNet = CallType::factory()->create(['key' => CallTypeEnum::OFF_NET]);
    attachToPackage($user, $onNet);
    attachToPackage($user, $offNet);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/earnings')
        ->assertOk();

    expect($response->json('data.usage_verified'))->toBeFalse()
        ->and($response->json('data.record_source'))->toBe('daily_pool')
        ->and($response->json('data.daily_allowance_minutes'))->toEqual(60.0)
        ->and($response->json('data.share_term_allocated_minutes'))->toEqual(600.0)
        ->and($response->json('data.remaining_minutes'))->toEqual(600.0)
        ->and($response->json('data.share_minutes_available_now'))->toEqual(20.0)
        ->and(count($response->json('data.records')))->toBeGreaterThan(0);

    foreach ($response->json('data.records') as $row) {
        expect($row['in_package'])->toBeTrue();
        expect($row['claimed'])->toBeFalse();
        expect($row['usage_verified'])->toBeFalse();
        expect($row['record_source'])->toBe('daily_pool');
        expect($row)->toHaveKey('blocked_reason');
    }
});

test('earnings API offers a proportional partial claim when a call exceeds accrued share minutes', function () {
    $user = shareEntitledUser();
    $onNet = CallType::factory()->create(['key' => CallTypeEnum::ON_NET]);
    attachToPackage($user, $onNet);
    $record = CallRecord::factory()->create([
        'call_type_id' => $onNet->id,
        'duration_minutes' => 25,
        'earnings' => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/earnings')
        ->assertOk();

    $row = collect($response->json('data.records'))->firstWhere('id', $record->id);

    expect($row['blocked_reason'])->toBeNull()
        ->and($row['can_claim'])->toBeTrue()
        ->and($row['duration_minutes'])->toEqual(25.0)
        ->and($row['claimable_minutes'])->toEqual(20.0)
        ->and($row['amount_usd'])->toBe(0.4)
        ->and($response->json('data.share_minutes_available_now'))->toBe(20.0)
        ->and($response->json('data.share_minutes_remaining'))->toBe(600.0);

    $claim = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/earnings/'.$record->id.'/claim')
        ->assertOk()
        ->assertJsonPath('data.claimed_minutes', 20)
        ->assertJsonPath('data.pool_duration_minutes', 25);

    expect((float) $claim->json('data.amount_usd'))->toBe(0.4)
        ->and((float) CallClaim::query()->where('user_id', $user->id)->where('call_record_id', $record->id)->value('duration_minutes'))->toBe(20.0)
        ->and((float) DB::table('share_minute_allocations')->where('share_subscription_id', app(ClaimService::class)->subscription($user)->id)->sum('minutes'))->toBe(20.0);
});

test('claiming an in-package call credits the shares wallet and remains unique per user and record', function () {
    $user = shareEntitledUser();
    $onNet = CallType::factory()->create(['key' => CallTypeEnum::ON_NET]);
    attachToPackage($user, $onNet);

    $record = CallRecord::factory()->create([
        'call_type_id' => $onNet->id,
        'duration_minutes' => 5,
        'earnings' => 1,
        'rate_per_minute' => 0.05,
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/earnings')
        ->assertOk();

    $claim = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/earnings/'.$record->id.'/claim')
        ->assertOk()
        ->assertJsonPath('data.wallet', 'shareholder');

    expect((float) $claim->json('data.amount_usd'))->toBeGreaterThan(0)
        ->and((float) $user->refresh()->shareholder_balance)->toBeGreaterThan(0);

    expect(Transaction::where('user_id', $user->id)
        ->where('type', TransactionType::CALL_EARNING)
        ->exists())->toBeTrue();

    // The same user cannot claim the record twice.
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/earnings/'.$record->id.'/claim')
        ->assertStatus(422);
});

test('different users can each claim the same shared call record', function () {
    $first = shareEntitledUser();
    $second = shareEntitledUser();
    $onNet = CallType::factory()->create(['key' => CallTypeEnum::ON_NET]);
    attachToPackage($first, $onNet);
    attachToPackage($second, $onNet);
    $record = CallRecord::factory()->create([
        'call_type_id' => $onNet->id,
        'duration_minutes' => 5,
        'earnings' => 1,
        'rate_per_minute' => 0.05,
    ]);

    foreach ([$first, $second] as $user) {
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/earnings/'.$record->id.'/claim')
            ->assertOk();
    }

    expect(CallClaim::query()->where('call_record_id', $record->id)->count())->toBe(2);
});

test('a claim is proportionally limited to the remaining daily earning cap', function () {
    $user = shareEntitledUser(packageAttributes: ['max_daily_shareholder_earning' => 0.1]);
    $onNet = CallType::factory()->create([
        'key' => CallTypeEnum::ON_NET,
        'claimable_percentage' => 100,
    ]);
    attachToPackage($user, $onNet);
    $record = CallRecord::factory()->create([
        'call_type_id' => $onNet->id,
        'duration_minutes' => 10,
        'earnings' => 1,
        'rate_per_minute' => 0.05,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/earnings/'.$record->id.'/claim')
        ->assertOk()
        ->assertJsonPath('data.amount_usd', 0.1)
        ->assertJsonPath('data.claimed_minutes', 1)
        ->assertJsonPath('data.pool_duration_minutes', 10);

    expect((float) CallClaim::query()->where('user_id', $user->id)->where('call_record_id', $record->id)->value('duration_minutes'))->toBe(1.0)
        ->and((float) $user->refresh()->shareholder_balance)->toBe(0.1);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/earnings')
        ->assertJsonPath('data.share_minutes_remaining', 599.0)
        ->assertJsonPath('data.daily_earning_cap_remaining_usd', 0.0);
});

test('claim-all processes the full daily pool and respects partial minutes and the daily cap', function () {
    $user = shareEntitledUser(packageAttributes: [
        'monthly_share_minutes' => 600,
        'max_daily_shareholder_earning' => 1.5,
    ]);
    $included = CallType::factory()->create([
        'key' => CallTypeEnum::ON_NET,
        'claimable_percentage' => 100,
    ]);
    $excluded = CallType::factory()->create(['key' => CallTypeEnum::INTERNATIONAL]);
    attachToPackage($user, $included);

    $eligibleRecords = collect(range(1, 3))->map(fn (): CallRecord => CallRecord::factory()->create([
        'call_type_id' => $included->id,
        'duration_minutes' => 8,
        'earnings' => 1,
        'rate_per_minute' => 0.125,
    ]));
    $excludedRecord = CallRecord::factory()->create([
        'call_type_id' => $excluded->id,
        'duration_minutes' => 8,
        'earnings' => 100,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/earnings/claim-all')
        ->assertOk()
        ->assertJsonPath('data.amount_usd', 1.5)
        ->assertJsonPath('data.records_claimed', 2)
        ->assertJsonPath('data.wallet', 'shareholder')
        ->assertJsonPath('data.record_source', 'daily_pool')
        ->assertJsonPath('data.usage_verified', false);

    $claims = CallClaim::query()->where('user_id', $user->id)->get();

    expect($claims)->toHaveCount(2)
        ->and((float) $claims->sum('amount'))->toBe(1.5)
        ->and((float) $claims->sum('duration_minutes'))->toBe(12.0)
        ->and($eligibleRecords->filter(fn (CallRecord $record): bool => $claims->pluck('call_record_id')->contains($record->id)))->toHaveCount(2)
        ->and($excludedRecord->claims()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($response->json('data.amount_usd'))->toBe(1.5);
});

test('claims require a current share term even when a package is assigned', function () {
    $user = activatedUser();
    $onNet = CallType::factory()->create(['key' => CallTypeEnum::ON_NET]);
    attachToPackage($user, $onNet);
    $record = CallRecord::factory()->create([
        'call_type_id' => $onNet->id,
        'duration_minutes' => 5,
        'earnings' => 1,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/earnings/'.$record->id.'/claim')
        ->assertStatus(403);
});

test('call types outside the package are hidden and unclaimable', function () {
    $user = shareEntitledUser();
    $included = CallType::factory()->create(['key' => CallTypeEnum::ON_NET]);
    $excluded = CallType::factory()->create(['key' => CallTypeEnum::INTERNATIONAL]);
    attachToPackage($user, $included);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/earnings')
        ->assertOk();

    // Every listed row belongs to an in-package call type.
    $listedTypes = collect($response->json('data.records'))->pluck('type')->unique()->values();
    expect($listedTypes)->toContain($included->name)
        ->and($listedTypes)->not->toContain($excluded->name);

    // Grab a pool record of the excluded type straight from the DB and try to claim it.
    $excludedRecord = CallRecord::where('call_type_id', $excluded->id)->first();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/earnings/'.$excludedRecord->id.'/claim')
        ->assertStatus(403);
});

test('unknown call records 404', function () {
    $user = activatedUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/earnings/00000000-0000-7000-8000-000000000000/claim')
        ->assertStatus(404);
});
