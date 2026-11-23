<?php

use App\Models\CallType;
use App\Models\Package;
use App\Services\ShareQuotaService;

test('daily caps become minimum centi-minute monthly quotas at current claimable rates', function () {
    $quotas = app(ShareQuotaService::class);

    $onNetMonthlyMinutes = $quotas->minimumMonthlyMinutesForDailyCap(0.65, 0.0104, 70);
    $offNetMonthlyMinutes = $quotas->minimumMonthlyMinutesForDailyCap(2.00, 0.102, 85);

    expect($onNetMonthlyMinutes)->toBe(2678.70)
        ->and($offNetMonthlyMinutes)->toBe(692.10)
        ->and($quotas->dailyBudgetUnits($onNetMonthlyMinutes, 30))->toBe(8929)
        ->and($quotas->dailyBudgetUnits($offNetMonthlyMinutes, 30))->toBe(2307);
});

test('monthly share minutes are spread across short and long terms without changing their daily pace', function () {
    $quotas = app(ShareQuotaService::class);
    $monthlyMinutes = 2678.70;

    expect($quotas->termAllocation($monthlyMinutes, 45))->toBe(4018.05)
        ->and($quotas->dailyBudgetUnits($monthlyMinutes, 45))->toBe(8929)
        ->and($quotas->termAllocation($monthlyMinutes, 7))->toBe(625.03)
        ->and($quotas->dailyBudgetUnits($monthlyMinutes, 7))->toBe(8929);
});

test('a positive daily cap is infeasible without a positive claimable rate or valid centi-minute bounds', function () {
    $quotas = app(ShareQuotaService::class);
    $invalidBounds = CallType::factory()->make([
        'min_minutes' => 0.011,
        'max_minutes' => 0.015,
        'max_daily_shareholder_earning' => 1,
    ]);

    expect($quotas->minimumMonthlyMinutesForDailyCap(1, 0.01, 0))->toBeNull()
        ->and($quotas->minimumMonthlyMinutesForDailyCap(0, 0, 0))->toBe(0.0)
        ->and($quotas->hasValidDurationBounds($invalidBounds))->toBeFalse()
        ->and($quotas->minimumMonthlyMinutesForCallType($invalidBounds))->toBeNull();
});

test('a package uses its best active available call type to determine the shared quota floor', function () {
    $slowType = CallType::factory()->create([
        'rate_per_minute' => 0.01,
        'claimable_percentage' => 50,
    ]);
    $fastType = CallType::factory()->create([
        'rate_per_minute' => 0.10,
        'claimable_percentage' => 80,
    ]);
    $unavailableType = CallType::factory()->create([
        'rate_per_minute' => 10,
        'claimable_percentage' => 100,
    ]);
    $package = Package::factory()->create(['max_daily_shareholder_earning' => 1]);
    $package->callTypes()->attach($slowType->id, [
        'is_available' => true,
        'claimable_percentage' => 50,
    ]);
    $package->callTypes()->attach($fastType->id, [
        'is_available' => true,
        'claimable_percentage' => 80,
    ]);
    $package->callTypes()->attach($unavailableType->id, [
        'is_available' => false,
        'claimable_percentage' => 100,
    ]);
    $package->load('callTypes');

    $minimum = app(ShareQuotaService::class)->minimumMonthlyMinutesForPackage($package);

    expect($minimum)->toBe(375.0);
});
