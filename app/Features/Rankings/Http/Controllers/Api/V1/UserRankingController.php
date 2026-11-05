<?php

namespace App\Features\Rankings\Http\Controllers\Api\V1;

use App\Application\Actions\Rankings\CheckRankEligibility;
use App\Application\Actions\Rankings\RankEligibility;
use App\Models\UserRankQualification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserRankingController
{
    public function show(Request $request, CheckRankEligibility $checker, RankEligibility $eligibility): JsonResponse
    {
        $user = $request->user();
        $qualifications = UserRankQualification::query()->with('level')
            ->where('user_id', $user->id)->latest('qualified_at')->get()
            ->map(fn ($qualification) => [
                'id' => $qualification->id,
                'level' => $qualification->level->only(['id', 'level', 'name', 'icon', 'monthly_reward_usd', 'reward_duration_months']),
                'qualified_at' => $qualification->qualified_at,
                'next_reward_at' => $qualification->next_reward_at,
                'rewards_paid' => $qualification->rewards_paid,
                'monthly_reward_usd' => (float) $qualification->monthly_reward_usd_at_qualification,
                'reward_duration_months' => (int) $qualification->reward_duration_months_at_qualification,
                'status' => $qualification->status,
            ]);

        return response()->json(['data' => [
            'enabled' => $checker->enabled(),
            'progress' => $eligibility->metrics($user),
            'levels' => $eligibility->levelsFor($user),
            'qualifications' => $qualifications,
        ]]);
    }
}
