<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\AllocatedNumber;
use App\Models\CallClaim;
use App\Models\CallRecord;
use App\Models\CallType;
use App\Models\ShareSubscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClaimService
{
    public function __construct(private ShareSubscriptionService $subscriptions) {}

    public function subscription(User $user): ?ShareSubscription
    {
        return $this->subscriptions->current($user);
    }

    public function hasActiveSubscription(User $user): bool
    {
        return $this->subscription($user) !== null;
    }

    public function inPackage(User $user, CallType $callType, ?ShareSubscription $subscription = null): bool
    {
        $subscription ??= $this->subscription($user);
        if ($subscription === null || ! $callType->is_active) {
            return false;
        }

        // Standalone share access unlocks only the CallType that was purchased or coupon-activated.
        if ($subscription->call_type_id !== null) {
            return $subscription->call_type_id === $callType->id;
        }

        if ($subscription->package_id === null || $user->package_id !== $subscription->package_id) {
            return false;
        }

        $user->loadMissing('package.callTypes');
        $package = $user->package;
        if ($package === null || $package->id !== $subscription->package_id) {
            return false;
        }

        return (bool) $package->callTypes
            ->first(fn (CallType $candidate): bool => $candidate->id === $callType->id && (bool) $candidate->pivot->is_available);
    }

    public function claimPercent(User $user, CallType $callType, ?ShareSubscription $subscription = null): float
    {
        if (! $this->inPackage($user, $callType, $subscription)) {
            return 0.0;
        }

        $subscription ??= $this->subscription($user);
        if ($subscription?->call_type_id !== null) {
            return (float) $callType->claimable_percentage;
        }

        $user->loadMissing('package.callTypes');
        $pivot = $user->package?->callTypes->firstWhere('id', $callType->id)?->pivot;

        return (float) ($pivot?->claimable_percentage ?? $callType->claimable_percentage);
    }

    /** Minutes of this record that fit both the user's accrued share quota and remaining daily cap. */
    public function claimableMinutes(
        User $user,
        CallRecord $record,
        ?ShareSubscription $subscription = null,
        ?float $availableMinutes = null,
        ?float $earningRemaining = null,
    ): float {
        $record->loadMissing('callType');
        $subscription ??= $this->subscription($user);

        if ($subscription === null || ! $record->callType) {
            return 0.0;
        }

        $recordDuration = round((float) $record->duration_minutes, 2);
        $percentage = $this->claimPercent($user, $record->callType, $subscription);
        if ($recordDuration <= 0 || (float) $record->earnings <= 0 || $percentage <= 0) {
            return 0.0;
        }

        $availableMinutes ??= $this->availableMinutes($user, $subscription);
        $earningRemaining ??= $this->remainingEarning($user, $subscription);
        if ($availableMinutes <= 0 || $earningRemaining <= 0) {
            return 0.0;
        }

        $durationUnits = (int) floor(min($recordDuration, $availableMinutes) * 100 + 1e-9);
        if ($durationUnits <= 0) {
            return 0.0;
        }

        $fullClaimable = $this->amountForDuration($record, $recordDuration, $percentage);
        if ($fullClaimable <= 0) {
            return 0.0;
        }

        if ($fullClaimable > $earningRemaining + 1e-9) {
            $capLimitedMinutes = $recordDuration * $earningRemaining / $fullClaimable;
            $durationUnits = min($durationUnits, (int) floor($capLimitedMinutes * 100 + 1e-9));
        }

        // Round down to the stored 0.01-minute precision, then guard against monetary rounding.
        while ($durationUnits > 0) {
            $minutes = round($durationUnits / 100, 2);
            $amount = $this->amountForDuration($record, $minutes, $percentage);
            if ($amount > 0 && $amount <= $earningRemaining + 1e-9) {
                return $minutes;
            }

            $durationUnits--;
        }

        return 0.0;
    }

    /**
     * Allocate the current minute and earning limits across rows for accurate aggregate summaries.
     * Higher-return records are considered first, with chronological/ID tie-breakers for determinism.
     *
     * @param iterable<CallRecord> $records
     * @return list<array{record: CallRecord, minutes: float, amount: float}>
     */
    public function claimableBreakdown(
        User $user,
        iterable $records,
        ?ShareSubscription $subscription = null,
        ?float $availableMinutes = null,
        ?float $earningRemaining = null,
    ): array {
        $subscription ??= $this->subscription($user);
        if ($subscription === null) {
            return [];
        }

        $availableMinutes ??= $this->availableMinutes($user, $subscription);
        $earningRemaining ??= $this->remainingEarning($user, $subscription);
        if ($availableMinutes <= 0 || $earningRemaining <= 0) {
            return [];
        }

        $ratePerMinute = function (CallRecord $record) use ($user, $subscription): float {
            $record->loadMissing('callType');
            $duration = (float) $record->duration_minutes;
            if (! $record->callType || $duration <= 0) {
                return 0.0;
            }

            $percentage = $this->claimPercent($user, $record->callType, $subscription);

            return $this->amountForDuration($record, $duration, $percentage) / $duration;
        };

        $orderedRecords = collect($records)
            ->filter(fn ($record): bool => $record instanceof CallRecord)
            ->sort(function (CallRecord $first, CallRecord $second) use ($ratePerMinute): int {
                $rateOrder = $ratePerMinute($second) <=> $ratePerMinute($first);
                if ($rateOrder !== 0) {
                    return $rateOrder;
                }

                $timeOrder = ($first->created_at?->getTimestamp() ?? 0) <=> ($second->created_at?->getTimestamp() ?? 0);

                return $timeOrder !== 0 ? $timeOrder : strcmp((string) $first->id, (string) $second->id);
            });

        $breakdown = [];
        foreach ($orderedRecords as $record) {
            $minutes = $this->claimableMinutes($user, $record, $subscription, $availableMinutes, $earningRemaining);
            if ($minutes <= 0) {
                continue;
            }

            $amount = $this->claimableAmountForMinutes($user, $record, $minutes, $subscription);
            if ($amount <= 0) {
                continue;
            }

            $breakdown[] = [
                'record' => $record,
                'minutes' => $minutes,
                'amount' => $amount,
            ];
            $availableMinutes = max(0, round($availableMinutes - $minutes, 2));
            $earningRemaining = max(0, round($earningRemaining - $amount, 6));
        }

        return $breakdown;
    }

    public function claimableAmount(
        User $user,
        CallRecord $record,
        ?ShareSubscription $subscription = null,
        ?float $availableMinutes = null,
        ?float $earningRemaining = null,
    ): float {
        $minutes = $this->claimableMinutes($user, $record, $subscription, $availableMinutes, $earningRemaining);

        return $this->claimableAmountForMinutes($user, $record, $minutes, $subscription);
    }

    /** Compute the row's proportional payout for a selected duration without mutating the pool record. */
    public function claimableAmountForMinutes(
        User $user,
        CallRecord $record,
        float $minutes,
        ?ShareSubscription $subscription = null,
    ): float {
        $record->loadMissing('callType');
        if (! $record->callType) {
            return 0.0;
        }

        return $this->amountForDuration($record, $minutes, $this->claimPercent($user, $record->callType, $subscription));
    }

    private function amountForDuration(CallRecord $record, float $minutes, float $percentage): float
    {
        $recordDuration = (float) $record->duration_minutes;
        if ($recordDuration <= 0 || $minutes <= 0 || $percentage <= 0) {
            return 0.0;
        }

        $partialEarnings = round((float) $record->earnings * min(1, $minutes / $recordDuration), 6);

        return round($partialEarnings * $percentage / 100, 6);
    }

    /** Explicit share quota for the current term, including its linked top-ups. */
    public function allocatedMinutes(User $user, ?ShareSubscription $subscription = null): float
    {
        $subscription ??= $this->subscription($user);
        if ($subscription === null) {
            return 0.0;
        }

        $topUps = (float) $subscription->minuteTopUps()->shareActive()->sum('share_minutes');

        return round((float) $subscription->allocated_minutes + $topUps, 2);
    }

    /** Existing eSIM daily-call allowance, retained as a separate non-share entitlement. */
    public function dailyAllowance(User $user): float
    {
        $base = (float) ($user->loadMissing('package')->package?->daily_call_minutes ?? 0);

        return round($base + $user->activeMinuteBonus(), 2);
    }

    public function claimedMinutesInTerm(User $user, ?ShareSubscription $subscription = null): float
    {
        $subscription ??= $this->subscription($user);
        if ($subscription === null) {
            return 0.0;
        }

        return (float) $subscription->claims()->sum('duration_minutes');
    }

    /** Total unspent term quota, including currently active share-minute top-ups. */
    public function remainingMinutes(User $user, ?ShareSubscription $subscription = null): float
    {
        $subscription ??= $this->subscription($user);
        if ($subscription === null) {
            return 0.0;
        }

        return round(array_sum(array_column($this->remainingMinutePools($subscription), 'minutes')), 2);
    }

    /** Minutes the user can claim against now: accrued base quota plus active top-ups. */
    public function availableMinutes(User $user, ?ShareSubscription $subscription = null): float
    {
        $subscription ??= $this->subscription($user);
        if ($subscription === null) {
            return 0.0;
        }

        return round(array_sum(array_column($this->remainingMinutePools($subscription, paceBase: true), 'minutes')), 2);
    }

    /**
     * Return remaining base and currently-active top-up quota separately. Keeping provenance per
     * allocation means an expired top-up's already-used minutes do not silently consume base quota
     * or another top-up's remaining minutes.
     *
     * @return list<array{minute_top_up_id: ?string, minutes: float}>
     */
    private function remainingMinutePools(ShareSubscription $subscription, bool $paceBase = false): array
    {
        $baseUsed = (float) DB::table('share_minute_allocations')
            ->where('share_subscription_id', $subscription->id)
            ->whereNull('minute_top_up_id')
            ->sum('minutes');

        $pools = [];
        $topUps = $subscription->minuteTopUps()
            ->shareActive()
            ->orderBy('share_expires_at')
            ->orderBy('created_at')
            ->get(['id', 'share_minutes']);

        if ($topUps->isNotEmpty()) {
            $topUpUsage = DB::table('share_minute_allocations')
                ->where('share_subscription_id', $subscription->id)
                ->whereIn('minute_top_up_id', $topUps->modelKeys())
                ->selectRaw('minute_top_up_id, SUM(minutes) AS used_minutes')
                ->groupBy('minute_top_up_id')
                ->get()
                ->keyBy('minute_top_up_id');

            foreach ($topUps as $topUp) {
                $used = (float) ($topUpUsage->get($topUp->id)?->used_minutes ?? 0);
                $pools[] = [
                    'minute_top_up_id' => $topUp->id,
                    'minutes' => max(0, round((float) $topUp->share_minutes - $used, 2)),
                ];
            }
        }

        // Use the earliest-expiring top-ups first. The base allocation accrues evenly over the term.
        $baseLimit = $paceBase
            ? max($baseUsed, $this->accruedBaseMinutes($subscription))
            : (float) $subscription->allocated_minutes;

        $pools[] = [
            'minute_top_up_id' => null,
            'minutes' => max(0, round($baseLimit - $baseUsed, 2)),
        ];

        return $pools;
    }

    /** The term allocation becomes available in equal daily installments; unused installments roll forward. */
    private function accruedBaseMinutes(ShareSubscription $subscription): float
    {
        $allocated = (float) $subscription->allocated_minutes;
        $startsAt = $subscription->starts_at;
        $expiresAt = $subscription->expires_at;

        if ($allocated <= 0 || $startsAt === null || $expiresAt === null) {
            return 0.0;
        }

        $startTimestamp = $startsAt->getTimestamp();
        $termSeconds = $expiresAt->getTimestamp() - $startTimestamp;
        $elapsedSeconds = now()->getTimestamp() - $startTimestamp;

        if ($termSeconds <= 0 || $elapsedSeconds < 0) {
            return 0.0;
        }

        $termDays = max(1, (int) round($termSeconds / 86400));
        $elapsedDays = min($termDays, max(1, (int) floor($elapsedSeconds / 86400) + 1));

        return round($allocated * $elapsedDays / $termDays, 2);
    }

    /**
     * Allocate the selected claim duration across the base quota and active top-ups in order.
     *
     * @return list<array{minute_top_up_id: ?string, minutes: float}>|null
     */
    private function allocateClaimMinutes(ShareSubscription $subscription, float $duration): ?array
    {
        $remaining = round($duration, 2);
        if ($remaining <= 0) {
            return [];
        }

        $allocations = [];
        foreach ($this->remainingMinutePools($subscription, paceBase: true) as $pool) {
            $used = min($remaining, $pool['minutes']);
            if ($used <= 0) {
                continue;
            }

            $allocations[] = [
                'minute_top_up_id' => $pool['minute_top_up_id'],
                'minutes' => round($used, 2),
            ];
            $remaining = round($remaining - $used, 2);

            if ($remaining <= 0) {
                return $allocations;
            }
        }

        return null;
    }

    public function maxDailyEarning(User $user, ?ShareSubscription $subscription = null): float
    {
        $subscription ??= $this->subscription($user);

        return $subscription === null ? 0.0 : round((float) $subscription->daily_earning_cap, 6);
    }

    public function earnedToday(User $user, ?ShareSubscription $subscription = null): float
    {
        $subscription ??= $this->subscription($user);
        if ($subscription === null) {
            return 0.0;
        }

        return (float) $subscription->claims()->whereDate('claimed_at', today())->sum('amount');
    }

    public function remainingEarning(User $user, ?ShareSubscription $subscription = null): float
    {
        $cap = $this->maxDailyEarning($user, $subscription);
        if ($cap <= 0) {
            return 0.0;
        }

        return max(0, round($cap - $this->earnedToday($user, $subscription), 6));
    }

    public function underEarningCap(User $user): bool
    {
        return $this->remainingEarning($user) > 0;
    }

    public function hasClaimed(User $user, CallRecord $record): bool
    {
        return CallClaim::query()
            ->where('user_id', $user->id)
            ->where('call_record_id', $record->id)
            ->exists();
    }

    /** A clear reason helps the web and API avoid offering a top-up when the daily money cap is hit. */
    public function blockedReason(User $user, CallRecord $record, ?ShareSubscription $subscription = null): ?string
    {
        if ($this->hasClaimed($user, $record)) {
            return 'already_claimed';
        }

        $subscription ??= $this->subscription($user);
        if ($subscription === null) {
            return 'share_plan_inactive';
        }

        $record->loadMissing('callType');
        if (! $record->callType || ! $this->inPackage($user, $record->callType, $subscription)) {
            return 'call_type_not_in_package';
        }

        if (! $record->created_at?->isToday()) {
            return 'call_record_expired';
        }

        $remaining = $this->remainingMinutes($user, $subscription);
        if ($remaining <= 0) {
            return 'term_minutes_exhausted';
        }

        $available = $this->availableMinutes($user, $subscription);
        if ($available <= 0) {
            return 'share_minutes_not_accrued';
        }

        $earningRemaining = $this->remainingEarning($user, $subscription);
        if ($earningRemaining <= 0) {
            return 'daily_earning_cap';
        }

        $claimableMinutes = $this->claimableMinutes($user, $record, $subscription, $available, $earningRemaining);
        if ($claimableMinutes > 0) {
            return null;
        }

        $percentage = $this->claimPercent($user, $record->callType, $subscription);
        $maximumDuration = min((float) $record->duration_minutes, $available);
        $maximumAmount = $this->amountForDuration($record, $maximumDuration, $percentage);

        return $maximumAmount > $earningRemaining ? 'daily_earning_cap' : 'no_claimable_amount';
    }

    public function canClaim(User $user, CallRecord $record): bool
    {
        return $this->blockedReason($user, $record) === null;
    }

    public function claim(User $user, CallRecord $record): float
    {
        if (! $this->canClaim($user, $record)) {
            return 0.0;
        }

        return $this->recordClaim($user, $record);
    }

    public function claimBatch(User $user, iterable $records): float
    {
        $subscription = $this->subscription($user);
        if ($subscription === null) {
            return 0.0;
        }

        $candidateRecords = collect($records)
            ->filter(fn ($record): bool => $record instanceof CallRecord)
            ->values();
        if ($candidateRecords->isEmpty()) {
            return 0.0;
        }

        $alreadyClaimed = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->pluck('call_record_id')
            ->flip();
        $unclaimedRecords = $candidateRecords
            ->reject(fn (CallRecord $record): bool => $alreadyClaimed->has($record->id))
            ->values();

        $breakdown = $this->claimableBreakdown(
            $user,
            $unclaimedRecords,
            $subscription,
            $this->availableMinutes($user, $subscription),
            $this->remainingEarning($user, $subscription),
        );
        if ($breakdown === []) {
            return 0.0;
        }

        // Process the full candidate set, in the same high-return-first order used by the
        // aggregate preview. Each record is rechecked under the normal user/subscription locks.
        return (float) DB::transaction(function () use ($user, $breakdown): float {
            $total = 0.0;

            foreach ($breakdown as $candidate) {
                $total += $this->recordClaim($user, $candidate['record']);
            }

            return round($total, 6);
        });
    }

    private function recordClaim(User $user, CallRecord $record): float
    {
        return (float) DB::transaction(function () use ($user, $record): float {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if (! $lockedUser) {
                return 0.0;
            }

            $pool = CallRecord::query()->whereKey($record->id)->with('callType')->first();
            if (! $pool || ! $pool->created_at?->isToday()) {
                return 0.0;
            }

            if (CallClaim::query()
                ->where('user_id', $lockedUser->id)
                ->where('call_record_id', $pool->id)
                ->exists()) {
                return 0.0;
            }

            $subscription = $this->subscriptions->current($lockedUser, lock: true);
            if ($subscription === null || ! $pool->callType || ! $this->inPackage($lockedUser, $pool->callType, $subscription)) {
                return 0.0;
            }

            $claimableMinutes = $this->claimableMinutes($lockedUser, $pool, $subscription);
            if ($claimableMinutes <= 0) {
                return 0.0;
            }

            $minuteAllocations = $this->allocateClaimMinutes($subscription, $claimableMinutes);
            if ($minuteAllocations === null) {
                return 0.0;
            }

            $amount = $this->claimableAmountForMinutes($lockedUser, $pool, $claimableMinutes, $subscription);
            $remainingEarning = $this->remainingEarning($lockedUser, $subscription);

            // Recheck under the user/subscription locks before writing any claim or quota rows.
            if ($amount <= 0 || $amount > $remainingEarning + 1e-9) {
                return 0.0;
            }

            // Keep the legacy single-source pointer populated when a top-up funds any portion;
            // the allocation ledger below is authoritative when a claim spans multiple pools.
            $topUpAllocation = collect($minuteAllocations)
                ->first(fn (array $allocation): bool => $allocation['minute_top_up_id'] !== null);
            $sourceTopUpId = $topUpAllocation['minute_top_up_id'] ?? null;
            $network = AllocatedNumber::networkFor($pool->counterparty) ?? 'Nextel';
            $now = now();
            $claimId = (string) Str::uuid7();

            // `insertOrIgnore` preserves the existing per-user/record uniqueness under races while
            // still allowing a different user to claim the same shared pool record.
            $inserted = DB::table('call_claims')->insertOrIgnore([
                'id' => $claimId,
                'user_id' => $lockedUser->id,
                'share_subscription_id' => $subscription->id,
                'call_record_id' => $pool->id,
                'call_type_id' => $pool->call_type_id,
                'minute_top_up_id' => $sourceTopUpId,
                'duration_minutes' => $claimableMinutes,
                'amount' => $amount,
                'network' => $network,
                'claimed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted !== 1) {
                return 0.0;
            }

            $allocationRows = array_map(fn (array $allocation): array => [
                'call_claim_id' => $claimId,
                'share_subscription_id' => $subscription->id,
                'minute_top_up_id' => $allocation['minute_top_up_id'],
                'minutes' => $allocation['minutes'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $minuteAllocations);

            if ($allocationRows !== []) {
                DB::table('share_minute_allocations')->insert($allocationRows);
            }

            $lockedUser->increment('shareholder_balance', $amount);
            $lockedUser->increment('lifetime_earnings', $amount);

            Transaction::create([
                'user_id' => $lockedUser->id,
                'type' => TransactionType::CALL_EARNING,
                'direction' => 'credit',
                'amount' => $amount,
                'wallet' => 'shareholder',
                'status' => 'completed',
                'reference' => 'CLM-'.strtoupper(Str::random(10)),
                'description' => $pool->callType->name.' earnings claim',
                'meta' => [
                    'call_type' => $pool->callType->name,
                    'call_record_id' => $pool->id,
                    'share_subscription_id' => $subscription->id,
                    'pool_duration_minutes' => (float) $pool->duration_minutes,
                    'claimed_duration_minutes' => $claimableMinutes,
                    'network' => $network,
                ],
            ]);

            return $amount;
        });
    }
}
