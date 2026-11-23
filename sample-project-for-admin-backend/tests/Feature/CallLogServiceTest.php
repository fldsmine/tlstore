<?php

use App\Models\CallClaim;
use App\Models\CallRecord;
use App\Models\CallType;
use App\Models\ShareSubscription;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\ClaimService;

test('pool generation reaches a standalone daily cap with rows inside configured duration bounds', function () {
    $callType = CallType::factory()->create([
        'rate_per_minute' => 0.5,
        'claimable_percentage' => 50,
        'monthly_share_minutes' => 600,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 5,
        'min_minutes' => 1,
        'max_minutes' => 60,
        'min_per_batch' => 0,
        'max_per_batch' => 0,
    ]);

    $count = app(CallLogService::class)->generatePool(collect([$callType]));
    $records = CallRecord::query()->where('call_type_id', $callType->id)->get();

    expect($count)->toBe(1)
        ->and($records)->toHaveCount(1)
        ->and(round((float) $records->sum('duration_minutes'), 2))->toBe(20.0)
        ->and(round((float) $records->sum(fn (CallRecord $record): float => $record->earnings * 0.5), 2))->toBe(5.0)
        ->and((float) $records->first()->earnings)->toBe(round((float) $records->first()->duration_minutes * (float) $records->first()->rate_per_minute, 12));
});

test('the supplied On-Net and Off-Net caps are reached within their daily minute budgets', function () {
    $onNet = CallType::factory()->create([
        'rate_per_minute' => 0.0104,
        'claimable_percentage' => 70,
        'monthly_share_minutes' => 2678.70,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 0.65,
        'min_minutes' => 4,
        'max_minutes' => 8,
        'min_per_batch' => 0,
        'max_per_batch' => 0,
    ]);
    $offNet = CallType::factory()->create([
        'rate_per_minute' => 0.102,
        'claimable_percentage' => 85,
        'monthly_share_minutes' => 692.10,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 2.00,
        'min_minutes' => 4,
        'max_minutes' => 20,
        'min_per_batch' => 0,
        'max_per_batch' => 0,
    ]);

    $count = app(CallLogService::class)->generatePool(collect([$onNet, $offNet]));
    $onNetRows = CallRecord::query()->where('call_type_id', $onNet->id)->get();
    $offNetRows = CallRecord::query()->where('call_type_id', $offNet->id)->get();

    expect($count)->toBe($onNetRows->count() + $offNetRows->count())
        ->and($onNetRows->isNotEmpty())->toBeTrue()
        ->and($offNetRows->isNotEmpty())->toBeTrue()
        ->and(round((float) $onNetRows->sum('duration_minutes'), 2))->toBe(2678.70 / 30)
        ->and(round((float) $offNetRows->sum('duration_minutes'), 2))->toBe(692.10 / 30)
        ->and(round((float) $onNetRows->sum(fn (CallRecord $record): float => $record->earnings * 0.70), 2))->toBe(0.65)
        ->and(round((float) $offNetRows->sum(fn (CallRecord $record): float => $record->earnings * 0.85), 2))->toBe(2.00);

    foreach ($onNetRows->merge($offNetRows) as $record) {
        expect(abs((float) $record->earnings - ((float) $record->duration_minutes * (float) $record->rate_per_minute)))
            ->toBeLessThan(0.00000000001);
    }

    $onNetUser = User::factory()->create(['package_id' => null]);
    $offNetUser = User::factory()->create(['package_id' => null]);
    $startsAt = now();
    ShareSubscription::query()->create([
        'user_id' => $onNetUser->id,
        'call_type_id' => $onNet->id,
        'allocated_minutes' => 2678.70,
        'daily_earning_cap' => 0.65,
        'starts_at' => $startsAt,
        'expires_at' => $startsAt->copy()->addDays(30),
        'status' => 'active',
    ]);
    ShareSubscription::query()->create([
        'user_id' => $offNetUser->id,
        'call_type_id' => $offNet->id,
        'allocated_minutes' => 692.10,
        'daily_earning_cap' => 2.00,
        'starts_at' => $startsAt,
        'expires_at' => $startsAt->copy()->addDays(30),
        'status' => 'active',
    ]);

    $claims = app(ClaimService::class);
    $onNetClaimed = $claims->claimBatch($onNetUser, $onNetRows);
    $offNetClaimed = $claims->claimBatch($offNetUser, $offNetRows);
    $onNetLedger = (float) CallClaim::query()->where('user_id', $onNetUser->id)->sum('amount');
    $offNetLedger = (float) CallClaim::query()->where('user_id', $offNetUser->id)->sum('amount');

    expect($onNetClaimed)->toBeLessThanOrEqual(0.65)
        ->and(round($onNetClaimed, 2))->toBe(0.65)
        ->and($offNetClaimed)->toBeLessThanOrEqual(2.00)
        ->and(round($offNetClaimed, 2))->toBe(2.00)
        ->and($onNetLedger)->toBeLessThanOrEqual(0.65)
        ->and(round($onNetLedger, 2))->toBe(0.65)
        ->and($offNetLedger)->toBeLessThanOrEqual(2.00)
        ->and(round($offNetLedger, 2))->toBe(2.00);
});

test('pool rows use target-derived rates that remain consistent with duration times rate', function () {
    $callType = CallType::factory()->create([
        'rate_per_minute' => 0.5,
        'claimable_percentage' => 100,
        'monthly_share_minutes' => 600,
        'share_term_days' => 90,
        'max_daily_shareholder_earning' => 5,
        'min_minutes' => 1,
        'max_minutes' => 60,
        'min_per_batch' => 0,
        'max_per_batch' => 0,
    ]);

    $count = app(CallLogService::class)->generatePool(collect([$callType]));
    $record = CallRecord::query()->sole();
    $user = User::factory()->create(['package_id' => null]);
    $startsAt = now();
    ShareSubscription::query()->create([
        'user_id' => $user->id,
        'call_type_id' => $callType->id,
        'allocated_minutes' => 600,
        'daily_earning_cap' => 5,
        'starts_at' => $startsAt,
        'expires_at' => $startsAt->copy()->addDays(90),
        'status' => 'active',
    ]);

    $amount = app(ClaimService::class)->claim($user, $record);
    $claim = $record->claims()->firstOrFail();

    expect($count)->toBe(1)
        ->and((float) $record->duration_minutes)->toBe(6.67)
        ->and((float) $record->earnings)->toBe(5.0)
        ->and(abs((float) $record->earnings - ((float) $record->duration_minutes * (float) $record->rate_per_minute)))->toBeLessThan(0.00000000001)
        ->and($amount)->toBe(5.0)
        ->and((float) $claim->duration_minutes)->toBe(6.67);
});

test('infeasible daily caps throw before any pool rows are inserted', function () {
    $callType = CallType::factory()->create([
        'rate_per_minute' => 0.5,
        'claimable_percentage' => 50,
        'monthly_share_minutes' => 10,
        'share_term_days' => 30,
        'max_daily_shareholder_earning' => 5,
        'min_minutes' => 1,
        'max_minutes' => 60,
        'min_per_batch' => 0,
        'max_per_batch' => 0,
    ]);

    expect(fn () => app(CallLogService::class)->generatePool(collect([$callType])))
        ->toThrow(InvalidArgumentException::class)
        ->and(CallRecord::query()->count())->toBe(0);
});

test('an empty active CallType set returns zero without attempting pool insertion', function () {
    expect(app(CallLogService::class)->generatePool(collect()))->toBe(0)
        ->and(CallRecord::query()->count())->toBe(0);
});
