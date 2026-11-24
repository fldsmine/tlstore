<?php

namespace App\Features\Identity\Http\Controllers\Api\V1;

use App\Models\ReferralCode;
use App\Models\ReferralAttribution;
use App\Models\ReferralReward;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ReferralController
{
    public function code(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = $user->referralCodes()->where('status', 'active')->first();
        if (! $code) {
            $code = ReferralCode::query()->create([
                'user_id' => $user->id,
                'code' => Str::upper(Str::random(5)),
                'status' => 'active',
            ]);
        }

        $directIds = fn () => ReferralAttribution::query()->select('referred_user_id')->where('referrer_user_id', $user->id);
        $secondIds = fn () => ReferralAttribution::query()->select('referred_user_id')->whereIn('referrer_user_id', $directIds());
        $directCount = $user->sentReferrals()->count();
        $secondCount = ReferralAttribution::query()->whereIn('referrer_user_id', $directIds())->count();
        $thirdCount = ReferralAttribution::query()->whereIn('referrer_user_id', $secondIds())->count();
        $qualified = ReferralAttribution::query()->where('status', 'qualified')->where(function ($query) use ($user, $directIds, $secondIds): void {
            $query->where('referrer_user_id', $user->id)
                ->orWhereIn('referrer_user_id', $directIds())
                ->orWhereIn('referrer_user_id', $secondIds());
        })->count();

        return response()->json(['data' => [
            'code' => $code->code,
            'attributed_signups' => $directCount + $secondCount + $thirdCount,
            'qualified_signups' => $qualified,
            'direct_referrals' => $directCount,
            'level_2_referrals' => $secondCount,
            'level_3_referrals' => $thirdCount,
        ]]);
    }

    public function attributions(Request $request): JsonResponse
    {
        $requestedLevel = $request->query('level');
        if ($requestedLevel !== null && ! in_array((string) $requestedLevel, ['1', '2', '3'], true)) {
            return response()->json(['message' => 'The selected referral tier is invalid.'], 422);
        }
        $user = $request->user();
        $frontier = [(int) $user->id];
        $seen = [(int) $user->id => true];
        $network = collect();
        // The referral page is the direct-invite list by default. The team page
        // passes a tier explicitly and traverses only as far as that tier.
        $maxLevel = $requestedLevel === null ? 1 : (int) $requestedLevel;

        for ($level = 1; $level <= $maxLevel && $frontier !== []; $level++) {
            $rows = ReferralAttribution::query()
                ->whereIn('referrer_user_id', $frontier)
                ->with([
                    'referredUser:id,username,created_at',
                    'rewards' => fn ($query) => $query
                        ->where('beneficiary_user_id', $user->id)
                        ->select('id', 'referral_attribution_id', 'beneficiary_user_id', 'referral_level', 'subscription_plan_id', 'reward_percent', 'base_amount_minor', 'base_currency', 'reward_type', 'amount_minor', 'currency', 'status', 'created_at'),
                ])
                ->latest('attributed_at')->get();
            $nextFrontier = [];
            foreach ($rows as $row) {
                $referredId = (int) $row->referred_user_id;
                if (isset($seen[$referredId])) {
                    continue;
                }
                $seen[$referredId] = true;
                $row->setAttribute('referral_level', $level);
                $network->push($row);
                $nextFrontier[] = $referredId;
            }
            $frontier = $nextFrontier;
        }

        $network = $network->sortByDesc('attributed_at')->values();
        if ($requestedLevel !== null && (int) $requestedLevel > 1) {
            $network = $network->where('referral_level', (int) $requestedLevel)->values();
        }
        $perPage = max(1, min(100, (int) $request->query('per_page', 30)));
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $network->forPage($page, $perPage)->values(),
            $network->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json(['data' => $paginator]);
    }

    public function team(Request $request): JsonResponse
    {
        $user = $request->user();
        $frontier = [(int) $user->id];
        $seen = [(int) $user->id => true];
        $counts = [1 => 0, 2 => 0, 3 => 0];

        for ($level = 1; $level <= 3 && $frontier !== []; $level++) {
            $rows = ReferralAttribution::query()->whereIn('referrer_user_id', $frontier)->get(['referred_user_id']);
            $nextFrontier = [];
            foreach ($rows as $row) {
                $referredId = (int) $row->referred_user_id;
                if (isset($seen[$referredId])) {
                    continue;
                }
                $seen[$referredId] = true;
                $counts[$level]++;
                $nextFrontier[] = $referredId;
            }
            $frontier = $nextFrontier;
        }

        $rewardTotals = ReferralReward::query()
            ->where('beneficiary_user_id', $user->id)
            ->where('status', 'paid')
            ->selectRaw('referral_level, currency, SUM(amount_minor) as amount_minor, COUNT(*) as reward_count')
            ->groupBy('referral_level', 'currency')
            ->get()
            ->groupBy('referral_level');

        $tiers = [];
        foreach ([1, 2, 3] as $level) {
            $tiers[] = [
                'level' => $level,
                'member_count' => $counts[$level],
                'paid_amounts' => ($rewardTotals->get($level) ?? collect())->map(fn ($row) => [
                    'currency' => $row->currency,
                    'amount_minor' => (int) $row->amount_minor,
                    'reward_count' => (int) $row->reward_count,
                ])->values(),
            ];
        }

        return response()->json(['data' => ['tiers' => $tiers]]);
    }
}
