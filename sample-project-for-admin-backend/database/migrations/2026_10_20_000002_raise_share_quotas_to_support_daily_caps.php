<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DAYS_PER_MONTH = 30;

    private const MINUTE_PRECISION = 100;

    /**
     * Raise configured product quotas to the least 0.01-minute daily pace that can reach the
     * product's hard daily earning cap, using the CallType's stored payout rate and claim percent.
     * Existing active subscriptions inherit at least the current product cap and are raised to the
     * matching term allocation; no stored entitlement is reduced.
     */
    public function up(): void
    {
        DB::table('call_types')
            ->orderBy('id')
            ->chunkById(100, function ($callTypes): void {
                foreach ($callTypes as $callType) {
                    if (! $this->hasValidDurationBounds((float) $callType->min_minutes, (float) $callType->max_minutes)) {
                        continue;
                    }

                    $minimum = $this->minimumMonthlyMinutes(
                        (float) $callType->max_daily_shareholder_earning,
                        (float) $callType->rate_per_minute,
                        (float) $callType->claimable_percentage,
                    );

                    if ($minimum === null || (float) $callType->monthly_share_minutes + 1e-9 >= $minimum) {
                        continue;
                    }

                    DB::table('call_types')->where('id', $callType->id)->update([
                        'monthly_share_minutes' => $minimum,
                    ]);
                }
            }, 'id');

        DB::table('packages')
            ->orderBy('id')
            ->chunkById(100, function ($packages): void {
                foreach ($packages as $package) {
                    $minimum = $this->minimumPackageMonthlyMinutes(
                        (string) $package->id,
                        (float) $package->max_daily_shareholder_earning,
                    );

                    if ($minimum === null || (float) $package->monthly_share_minutes + 1e-9 >= $minimum) {
                        continue;
                    }

                    DB::table('packages')->where('id', $package->id)->update([
                        'monthly_share_minutes' => $minimum,
                    ]);
                }
            }, 'id');

        DB::table('share_subscriptions')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $startsAt = Carbon::parse($subscription->starts_at);
                    $expiresAt = Carbon::parse($subscription->expires_at);
                    $termDays = max(1, (int) round(($expiresAt->getTimestamp() - $startsAt->getTimestamp()) / 86400));

                    if ($subscription->call_type_id !== null) {
                        $callType = DB::table('call_types')->where('id', $subscription->call_type_id)->first();
                        if ($callType === null
                            || ! (bool) $callType->is_active
                            || ! $this->hasValidDurationBounds((float) $callType->min_minutes, (float) $callType->max_minutes)) {
                            continue;
                        }

                        $subscriptionCap = max(
                            (float) $subscription->daily_earning_cap,
                            (float) $callType->max_daily_shareholder_earning,
                        );
                        $capMinimum = $this->minimumMonthlyMinutes(
                            $subscriptionCap,
                            (float) $callType->rate_per_minute,
                            (float) $callType->claimable_percentage,
                        );
                        if ($capMinimum === null) {
                            continue;
                        }

                        $monthlyMinutes = max((float) $callType->monthly_share_minutes, $capMinimum);
                    } elseif ($subscription->package_id !== null) {
                        $package = DB::table('packages')->where('id', $subscription->package_id)->first();
                        if ($package === null) {
                            continue;
                        }

                        $subscriptionCap = max(
                            (float) $subscription->daily_earning_cap,
                            (float) $package->max_daily_shareholder_earning,
                        );
                        $capMinimum = $this->minimumPackageMonthlyMinutes((string) $package->id, $subscriptionCap);
                        if ($capMinimum === null) {
                            continue;
                        }

                        $monthlyMinutes = max((float) $package->monthly_share_minutes, $capMinimum);
                    } else {
                        continue;
                    }

                    $requiredAllocation = round($monthlyMinutes * $termDays / self::DAYS_PER_MONTH, 2);
                    $updates = [];
                    if ($requiredAllocation > (float) $subscription->allocated_minutes + 1e-9) {
                        $updates['allocated_minutes'] = $requiredAllocation;
                    }
                    if ($subscriptionCap > (float) $subscription->daily_earning_cap + 1e-9) {
                        $updates['daily_earning_cap'] = $subscriptionCap;
                    }

                    if ($updates !== []) {
                        DB::table('share_subscriptions')->where('id', $subscription->id)->update($updates);
                    }
                }
            }, 'id');
    }

    private function hasValidDurationBounds(float $minimumMinutes, float $maximumMinutes): bool
    {
        $minimumUnits = max(1, (int) ceil($minimumMinutes * self::MINUTE_PRECISION - 1e-9));
        $maximumUnits = (int) floor($maximumMinutes * self::MINUTE_PRECISION + 1e-9);

        return $maximumUnits >= $minimumUnits;
    }

    /**
     * Convert a daily cap to a recurring monthly quota. Null means a positive cap has no positive
     * claimable rate, so no finite number of minutes can make it reachable.
     */
    private function minimumMonthlyMinutes(float $dailyCap, float $ratePerMinute, float $claimablePercentage): ?float
    {
        if ($dailyCap <= 0) {
            return 0.0;
        }

        $claimableRate = $ratePerMinute * $claimablePercentage / 100;
        if ($claimableRate <= 0) {
            return null;
        }

        $dailyMinuteUnits = (int) ceil(($dailyCap / $claimableRate) * self::MINUTE_PRECISION - 1e-9);

        return round($dailyMinuteUnits * (self::DAYS_PER_MONTH / self::MINUTE_PRECISION), 2);
    }

    /** The package has one shared minute quota; use its best active, available CallType return. */
    private function minimumPackageMonthlyMinutes(string $packageId, float $dailyCap): ?float
    {
        if ($dailyCap <= 0) {
            return 0.0;
        }

        $minimums = DB::table('package_call_type as package_type')
            ->join('call_types as call_type', 'call_type.id', '=', 'package_type.call_type_id')
            ->where('package_type.package_id', $packageId)
            ->where('package_type.is_available', true)
            ->where('call_type.is_active', true)
            ->get([
                'call_type.rate_per_minute',
                'call_type.min_minutes',
                'call_type.max_minutes',
                'call_type.claimable_percentage as base_claimable_percentage',
                'package_type.claimable_percentage as package_claimable_percentage',
            ])
            ->filter(fn ($row): bool => $this->hasValidDurationBounds((float) $row->min_minutes, (float) $row->max_minutes))
            ->map(fn ($row): ?float => $this->minimumMonthlyMinutes(
                $dailyCap,
                (float) $row->rate_per_minute,
                $row->package_claimable_percentage !== null
                    ? (float) $row->package_claimable_percentage
                    : (float) $row->base_claimable_percentage,
            ))
            ->filter(fn (?float $minimum): bool => $minimum !== null)
            ->values();

        return $minimums->isEmpty() ? null : (float) $minimums->min();
    }

    /**
     * This balance change is intentionally non-reversing: subscribers may claim against the added
     * allowance after deployment, so a rollback must not remove minutes already granted or used.
     */
    public function down(): void {}
};
