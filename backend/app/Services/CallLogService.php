<?php

namespace App\Services;

use App\Models\AllocatedNumber;
use App\Models\CallRecord;
use App\Models\CallType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Random\Randomizer;

class CallLogService
{
    private const POOL_FACTOR = 3;


	public function ensureTodaysPool(): int
	{
		if ($this->poolExistsToday()) {
			return 0;
		}

		return (int) Cache::lock(
			'calls:pool:' . today()->toDateString(),
			20
		)->block(5, function (): int {
			return $this->poolExistsToday()
				? 0
				: $this->generatePool();
		});
	}

    public function poolExistsToday(): bool
    {
        return CallRecord::query()->whereDate('created_at', today())->exists();
    }


	public function generatePool(?Collection $callTypes = null): int
	{
		$callTypes ??= CallType::query()->active()->get();

		if ($callTypes->isEmpty()) {
			return 0;
		}

		$now = now();
		$rows = [];
		$totalMinutes = 0.0;
		$totalGrossEarnings = 0.0;
		$totalClaimableEarnings = 0.0;
		$random = new Randomizer();

		foreach ($callTypes as $type) {
			// 1. Calculate daily allowance and target earnings.
			$termDays = (int) $type->share_term_days;

			if ($termDays <= 0) {
				throw new \InvalidArgumentException(
					"Invalid share_term_days for {$type->name}."
				);
			}

			$dailyMinutes = round(
				(float) $type->monthly_share_minutes / $termDays,
				2
			);

			$claimableRate = (float) $type->claimable_percentage / 100;
			$targetEarning = (float) $type->max_daily_shareholder_earning;

			if ($dailyMinutes <= 0 || $claimableRate <= 0 || $claimableRate > 1) {
				throw new \InvalidArgumentException(
					"Invalid daily minutes or claimable percentage for {$type->name}."
				);
			}

			if ($targetEarning < 0) {
				throw new \InvalidArgumentException(
					"Invalid target earning for {$type->name}."
				);
			}

			// Convert minutes into integer hundredths to avoid floating-point
			// errors when distributing the duration across calls.
			$totalUnits = (int) round($dailyMinutes * 100);
			$minUnits = (int) round((float) $type->min_minutes * 100);
			$maxUnits = (int) round((float) $type->max_minutes * 100);

			if ($minUnits <= 0 || $maxUnits < $minUnits) {
				throw new \InvalidArgumentException(
					"Invalid call duration limits for {$type->name}."
				);
			}

			// 2. Find all feasible call counts.
			// Each call must be within min_minutes and max_minutes,
			// and their combined duration must equal the daily allowance.
			$minimumFeasibleCount = (int) ceil($totalUnits / $maxUnits);
			$maximumFeasibleCount = (int) floor($totalUnits / $minUnits);

			if ($minimumFeasibleCount > $maximumFeasibleCount) {
				throw new \InvalidArgumentException(
					"Daily allowance for {$type->name} cannot satisfy "
					. "the configured minimum and maximum call durations."
				);
			}

			// Preserve the configured batch preference when feasible.
			$preferredMin = max(
				1,
				(int) $type->min_per_batch * self::POOL_FACTOR
			);

			$preferredMax = max(
				$preferredMin,
				(int) $type->max_per_batch * self::POOL_FACTOR
			);

			$countMin = max($minimumFeasibleCount, $preferredMin);
			$countMax = min($maximumFeasibleCount, $preferredMax);

			// If the configured batch size conflicts with the minute budget,
			// prioritize the daily allowance and duration constraints.
			if ($countMin > $countMax) {
				$countMin = $minimumFeasibleCount;
				$countMax = $maximumFeasibleCount;
			}

			$count = random_int($countMin, $countMax);

			// 3. Randomly distribute the daily minutes among the calls.
			// Every call stays within its configured duration limits.
			$remainingUnits = $totalUnits;
			$durations = [];

			for ($i = 0; $i < $count; $i++) {
				$remainingCalls = $count - $i - 1;

				$minimumNow = max(
					$minUnits,
					$remainingUnits - ($remainingCalls * $maxUnits)
				);

				$maximumNow = min(
					$maxUnits,
					$remainingUnits - ($remainingCalls * $minUnits)
				);

				$units = $random->getInt($minimumNow, $maximumNow);

				$durations[] = $units / 100;
				$remainingUnits -= $units;
			}

			if ($remainingUnits !== 0) {
				throw new \LogicException(
					"Failed to allocate all daily minutes for {$type->name}."
				);
			}

			// 4. Calculate the exact gross earning pool.
			//
			// claimable percentage * gross earnings = target earning
			// gross earnings = target earning / claimable percentage
			$targetGross = round($targetEarning / $claimableRate, 12);

			// 5. Allocate gross earnings proportionally to call duration.
			// The last call receives the rounding remainder so that the
			// sum of all call earnings equals the gross target.
			$allocatedGross = 0.0;

			foreach ($durations as $index => $minutes) {
				if ($index === array_key_last($durations)) {
					$earnings = round(
						$targetGross - $allocatedGross,
						12
					);
				} else {
					$earnings = round(
						$targetGross * ($minutes / $dailyMinutes),
						12
					);

					$allocatedGross += $earnings;
				}

				$rate = $minutes > 0
					? round($earnings / $minutes, 12)
					: 0.0;

				$rows[] = [
					'id' => (string) Str::uuid7(),
					'call_type_id' => $type->id,
					'direction' => random_int(0, 1) === 1
						? 'incoming'
						: 'outgoing',
					'counterparty' => AllocatedNumber::randomCounterparty(),
					'duration_minutes' => $minutes,
					'rate_per_minute' => $rate,
					'earnings' => $earnings,
					'created_at' => $now,
					'updated_at' => $now,
				];
			}

			$totalMinutes += $dailyMinutes;
			$totalGrossEarnings += $targetGross;
			$totalClaimableEarnings += $targetEarning;
		}

		// 6. Insert records efficiently.
		foreach (array_chunk($rows, 500) as $chunk) {
			CallRecord::insert($chunk);
		}
		
		
		Log::info('Daily call pool generated', [
			'count' => count($rows),
			'total_minutes' => round($totalMinutes, 2),
			'total_gross_earnings' => round($totalGrossEarnings, 12),
			'total_claimable_earnings' => round($totalClaimableEarnings, 12),
		]);

		return count($rows);
	}

}
