<?php

namespace App\Services;

use App\Models\CallType;
use App\Models\Package;

/**
 * Shared arithmetic for the recurring share-minute quota and the configured daily payout cap.
 * The cap remains a hard ceiling; this service computes the minimum minute pace needed to make
 * that ceiling reachable at the configured call rate and claim percentage.
 */
class ShareQuotaService
{
    private const DAYS_PER_MONTH = 30;

    private const MINUTE_PRECISION = 100;

    /**
     * Minimum recurring monthly minutes that support one configured daily cap.
     *
     * Minutes are claimable in 0.01-minute increments, so the daily requirement is rounded up to
     * the next hundredth before converting back to a 30-day monthly quota. A null result means the
     * cap cannot be funded at the supplied rate/percentage; a zero cap needs no quota.
     */
    public function minimumMonthlyMinutesForDailyCap(float $dailyCap, float $ratePerMinute, float $claimablePercentage): ?float
    {
        if ($dailyCap <= 0) {
            return 0.0;
        }

        $claimableRate = $ratePerMinute * $claimablePercentage / 100;
        if ($claimableRate <= 0) {
            return null;
        }

        $dailyMinuteUnits = (int) ceil(($dailyCap / $claimableRate) * self::MINUTE_PRECISION - 1e-9);

        // One hundredth of a daily minute corresponds to 0.30 monthly minutes.
        return round($dailyMinuteUnits * (self::DAYS_PER_MONTH / self::MINUTE_PRECISION), 2);
    }

    /** A recurring monthly quota prorated across a term using the fixed 30-day month basis. */
    public function termAllocation(float $monthlyMinutes, int $termDays): float
    {
        return round($monthlyMinutes * max(0, $termDays) / self::DAYS_PER_MONTH, 2);
    }

    /**
     * The number of claimable hundredths accrued per day for a term's rounded allocation.
     * This matches ShareSubscriptionService and ClaimService, including short/long terms.
     */
    public function dailyBudgetUnits(float $monthlyMinutes, int $termDays): int
    {
        if ($monthlyMinutes <= 0 || $termDays <= 0) {
            return 0;
        }

        $termMinutes = $this->termAllocation($monthlyMinutes, $termDays);

        return max(0, (int) floor(($termMinutes / $termDays) * self::MINUTE_PRECISION + 1e-9));
    }

    public function hasValidDurationBounds(CallType $callType): bool
    {
        $minimumUnits = max(1, (int) ceil((float) $callType->min_minutes * self::MINUTE_PRECISION - 1e-9));
        $maximumUnits = (int) floor((float) $callType->max_minutes * self::MINUTE_PRECISION + 1e-9);

        return $maximumUnits >= $minimumUnits;
    }

    public function minimumMonthlyMinutesForCallType(CallType $callType, ?float $dailyCap = null): ?float
    {
        if (! $this->hasValidDurationBounds($callType)) {
            return null;
        }

        return $this->minimumMonthlyMinutesForDailyCap(
            $dailyCap ?? (float) $callType->max_daily_shareholder_earning,
            (float) $callType->rate_per_minute,
            (float) $callType->claimable_percentage,
        );
    }

    /**
     * Return the smallest monthly quota that lets a package reach its cap through one of its
     * active, available CallTypes. Package quotas are shared across those types.
     */
    public function minimumMonthlyMinutesForPackage(Package $package, ?float $dailyCap = null): ?float
    {
        $dailyCap ??= (float) $package->max_daily_shareholder_earning;
        if ($dailyCap <= 0) {
            return 0.0;
        }

        $callTypes = $package->relationLoaded('callTypes')
            ? $package->callTypes
            : $package->callTypes()->get();

        $minimums = $callTypes
            ->filter(fn (CallType $type): bool => (bool) $type->is_active
                && (bool) $type->pivot?->is_available
                && $this->hasValidDurationBounds($type))
            ->map(function (CallType $type) use ($dailyCap): ?float {
                $percentage = $type->pivot?->claimable_percentage !== null
                    ? (float) $type->pivot->claimable_percentage
                    : (float) $type->claimable_percentage;

                return $this->minimumMonthlyMinutesForDailyCap(
                    $dailyCap,
                    (float) $type->rate_per_minute,
                    $percentage,
                );
            })
            ->filter(fn (?float $minimum): bool => $minimum !== null)
            ->values();

        return $minimums->isEmpty() ? null : (float) $minimums->min();
    }
}
