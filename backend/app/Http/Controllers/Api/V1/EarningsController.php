<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CallRecordRowResource;
use App\Models\AllocatedNumber;
use App\Models\CallClaim;
use App\Models\CallRecord;
use App\Models\CallType;
use App\Services\CallLogService;
use App\Services\ClaimService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    /** Today's claimable call pool + allowance summary (mirrors the Earnings page). */
    public function index(Request $request, ClaimService $claims): JsonResponse
    {
        $user = $request->user()->loadMissing('package.callTypes');

        app(CallLogService::class)->ensureTodaysPool();

        $pool = CallRecord::query()->whereDate('created_at', today())->with('callType')->get();

        $myClaims = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->get()
            ->keyBy('call_record_id');

        $subscription = $claims->subscription($user);
        $allocated = $claims->allocatedMinutes($user, $subscription);
        $remaining = $claims->remainingMinutes($user, $subscription);
        $available = $claims->availableMinutes($user, $subscription);
        $dailyCap = $claims->maxDailyEarning($user, $subscription);
        $dailyCapRemaining = $claims->remainingEarning($user, $subscription);

        $rows = $pool->map(function (CallRecord $r) use ($user, $claims, $myClaims, $subscription, $available, $dailyCapRemaining): array {
            $callType = $r->callType;
            $inPackage = $callType ? $claims->inPackage($user, $callType, $subscription) : false;
            $pct = $inPackage ? $claims->claimPercent($user, $callType, $subscription) : (float) $callType?->claimable_percentage;
            $myClaim = $myClaims->get($r->id);
            $claimed = $myClaim !== null;
            $network = AllocatedNumber::networkFor($r->counterparty) ?? 'Nextel';
            $claimableMinutes = $claimed
                ? 0.0
                : $claims->claimableMinutes($user, $r, $subscription, $available, $dailyCapRemaining);
            $blockedReason = $claimed ? 'already_claimed' : $claims->blockedReason($user, $r, $subscription);
            $potentialAmount = round((float) $r->earnings * $pct / 100, 6);
            $amount = $claimed
                ? (float) $myClaim->amount
                : ($claimableMinutes > 0
                    ? $claims->claimableAmountForMinutes($user, $r, $claimableMinutes, $subscription)
                    : $potentialAmount);

            return [
                'id' => $r->id,
                'type' => $callType?->name ?? 'Call',
                'amount' => $amount,
                'blockedReason' => $blockedReason,
                'earned' => (float) $r->earnings,
                'durationMinutes' => (float) $r->duration_minutes,
                'claimableMinutes' => $claimableMinutes,
                'claimedMinutes' => $claimed ? (float) $myClaim->duration_minutes : 0.0,
                'claimed' => $claimed,
                'claimedAt' => $myClaim?->claimed_at,
                'occurredAt' => $r->created_at,
                'inPackage' => $inPackage,
                'canClaim' => $blockedReason === null,
                'network' => $network,
            ];
        })->sortByDesc(fn (array $row): int => $row['occurredAt']?->timestamp ?? 0)->values();

        $inPackageRows = $rows->where('inPackage', true);
        $unclaimedRecords = $pool->reject(fn (CallRecord $record): bool => $myClaims->has($record->id));
        $claimableBreakdown = $claims->claimableBreakdown($user, $unclaimedRecords, $subscription, $available, $dailyCapRemaining);
        $unclaimedAmount = array_sum(array_column($claimableBreakdown, 'amount'));

        return ApiResponse::success('Earnings retrieved successfully.', [
            'records' => CallRecordRowResource::collection($inPackageRows->values()),
            'record_source' => 'daily_pool',
            'usage_verified' => false,
            'today_earnings_usd' => round((float) $rows->sum('amount'), 6),
            'claimed_today_usd' => round((float) $rows->where('claimed', true)->sum('amount'), 6),
            'unclaimed_usd' => round((float) $unclaimedAmount, 6),
            'unclaimable_usd' => round((float) $inPackageRows->where('claimed', false)->where('canClaim', false)->sum('amount'), 6),
            'daily_allowance_minutes' => $claims->dailyAllowance($user), // legacy eSIM daily allowance
            'share_term_allocated_minutes' => $allocated,
            'share_minutes_remaining' => $remaining,
            'share_minutes_available_now' => $available,
            'remaining_minutes' => $remaining, // compatibility alias; now the share-term remainder
            'daily_earning_cap_usd' => $dailyCap,
            'daily_earning_cap_remaining_usd' => $dailyCapRemaining,
            'share_plan_expires_at' => $subscription?->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Shareholder Portal view: today's call pool broken down per call type,
     * mirroring the web's Shareholder page (pool size, claimable vs claimed).
     */
    public function portal(Request $request, ClaimService $claims): JsonResponse
    {
        $user = $request->user()->loadMissing('package.callTypes');

        app(CallLogService::class)->ensureTodaysPool();

        $callTypes = CallType::query()->active()->ordered()->get();
        $poolRecords = CallRecord::query()->whereDate('created_at', today())->with('callType')->get();
        $pool = $poolRecords->groupBy('call_type_id');
        $subscription = $claims->subscription($user);
        $allocated = $claims->allocatedMinutes($user, $subscription);
        $remaining = $claims->remainingMinutes($user, $subscription);
        $available = $claims->availableMinutes($user, $subscription);
        $dailyCap = $claims->maxDailyEarning($user, $subscription);
        $dailyCapRemaining = $claims->remainingEarning($user, $subscription);

        $myClaimedIds = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->pluck('call_record_id')
            ->filter()
            ->flip();

        $myClaimedByType = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->get()
            ->groupBy('call_type_id');

        $claimableBreakdown = $claims->claimableBreakdown(
            $user,
            $poolRecords->reject(fn (CallRecord $record): bool => $myClaimedIds->has($record->id)),
            $subscription,
            $available,
            $dailyCapRemaining,
        );
        $claimableByType = collect($claimableBreakdown)
            ->groupBy(fn (array $row): ?string => $row['record']->call_type_id);

        $cards = $callTypes->map(function (CallType $ct) use ($pool, $myClaimedIds, $myClaimedByType, $claimableByType, $user, $claims, $subscription, $remaining): array {
            $recs = $pool->get($ct->id, collect());
            $inPackage = $claims->inPackage($user, $ct, $subscription);
            $pct = $claims->claimPercent($user, $ct, $subscription);

            $unclaimed = $recs->reject(fn (CallRecord $r) => $myClaimedIds->has($r->id));
            $claimableRows = $claimableByType->get($ct->id, collect());
            $claimable = $inPackage ? $claimableRows->sum('amount') : 0.0;
            $promptThreshold = max(0, (float) $ct->share_purchase_prompt_minutes);

            return [
                'call_type_id' => $ct->id,
                'name' => $ct->name,
                'in_package' => $inPackage,
                'share_percent' => $inPackage ? $pct : (float) $ct->claimable_percentage,
                'calls' => $recs->count(),
                'unclaimed_calls' => $inPackage ? $unclaimed->count() : 0,
                'claimable_calls' => $claimableRows->count(),
                'blocked_calls' => $inPackage ? max(0, $unclaimed->count() - $claimableRows->count()) : 0,
                'minutes' => round((float) $recs->sum('duration_minutes'), 1),
                'pool_earnings_usd' => round((float) $recs->sum('earnings'), 6),
                'claimable_usd' => round((float) $claimable, 6),
                'claimed_usd' => round((float) ($myClaimedByType->get($ct->id)?->sum('amount') ?? 0), 6),
                'share_purchase_prompt_minutes' => $promptThreshold,
                'share_purchase_prompt' => $inPackage && $promptThreshold > 0 && $remaining < $promptThreshold,
            ];
        })->values();

        return ApiResponse::success('Shareholder portal retrieved successfully.', [
            'cards' => $cards,
            'record_source' => 'daily_pool',
            'usage_verified' => false,
            'total_claimable_usd' => round((float) $cards->sum('claimable_usd'), 6),
            'total_claimed_usd' => round((float) $cards->sum('claimed_usd'), 6),
            'daily_allowance_minutes' => $claims->dailyAllowance($user), // legacy eSIM daily allowance
            'share_term_allocated_minutes' => $allocated,
            'share_minutes_remaining' => $remaining,
            'share_minutes_available_now' => $available,
            'remaining_minutes' => $remaining,
            'daily_earning_cap_usd' => $dailyCap,
            'daily_earning_cap_remaining_usd' => $dailyCapRemaining,
            'share_plan_expires_at' => $subscription?->expires_at?->toIso8601String(),
            'package_name' => $user->package?->name,
        ]);
    }

    public function claim(Request $request, string $record, ClaimService $claims): JsonResponse
    {
        $user = $request->user();

        $callRecord = CallRecord::query()->whereKey($record)->with('callType')->firstOrFail();

        if (! $claims->hasActiveSubscription($user)) {
            return ApiResponse::error('An active share term is required to claim. Start or buy one from the Shareholder Portal.', 403);
        }

        if (! $callRecord->callType || ! $claims->inPackage($user, $callRecord->callType)) {
            $typeName = $callRecord->callType?->name;

            return ApiResponse::error(
                $typeName !== null
                    ? $typeName.' is not included in your active share product. Choose that share product from the Shareholder Portal.'
                    : 'This call record has no eligible call type.',
                403,
            );
        }

        $blockedReason = $claims->blockedReason($user, $callRecord);
        if ($blockedReason !== null) {
            $message = match ($blockedReason) {
                'daily_earning_cap' => 'No part of this call fits within the remaining daily earning cap.',
                'term_minutes_exhausted' => 'No share-term minutes remain for this call.',
                'share_minutes_not_accrued' => 'Your next share-minute allowance has not accrued yet. Unused accrued minutes roll forward.',
                'already_claimed' => 'You have already claimed this call record.',
                'call_record_expired' => 'This call record is no longer available for claiming.',
                default => 'This call record cannot be claimed right now.',
            };

            return ApiResponse::error($message, 422);
        }

        $amount = $claims->claim($user, $callRecord);

        if ($amount <= 0) {
            return ApiResponse::error('Could not claim any eligible part of this call record.', 422);
        }

        $claim = CallClaim::query()
            ->where('user_id', $user->id)
            ->where('call_record_id', $callRecord->id)
            ->first();

        return ApiResponse::success('Claimed '.$amount.' USD from the daily pool, which is not independently verified against carrier records, to your Nextel Shares wallet.', [
            'amount_usd' => round($amount, 6),
            'claimed_minutes' => (float) ($claim?->duration_minutes ?? 0),
            'pool_duration_minutes' => (float) $callRecord->duration_minutes,
            'wallet' => 'shareholder',
            'record_source' => 'daily_pool',
            'usage_verified' => false,
        ]);
    }

    /** Claim all eligible records in today's pool, subject to the normal term-minute and daily-cap checks. */
    public function claimAll(Request $request, ClaimService $claims): JsonResponse
    {
        $user = $request->user()->loadMissing('package.callTypes');
        app(CallLogService::class)->ensureTodaysPool();

        $records = CallRecord::query()
            ->whereDate('created_at', today())
            ->with('callType')
            ->get();
        $claimedBefore = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->count();

        $amount = $claims->claimBatch($user, $records);
        $claimedAfter = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->count();
        $recordCount = max(0, $claimedAfter - $claimedBefore);

        return ApiResponse::success(
            $amount > 0
                ? 'Claimed '.$amount.' USD from eligible records in the daily pool, which is not independently verified against carrier records, to your Nextel Shares wallet.'
                : 'No eligible call earnings are available within your current share-minute and daily-cap limits.',
            [
                'amount_usd' => round($amount, 6),
                'records_claimed' => $recordCount,
                'wallet' => 'shareholder',
                'record_source' => 'daily_pool',
                'usage_verified' => false,
            ],
        );
    }
}
