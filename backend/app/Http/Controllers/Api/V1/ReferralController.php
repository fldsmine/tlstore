<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReferralResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $term = trim((string) $request->input('search', ''));
        $matches = fn ($query) => $query->when($term !== '', function ($q) use ($term): void {
            $q->where(fn ($w) => $w->where('username', 'like', '%'.$term.'%')
                ->orWhere('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%'));
        });

        $direct = $matches($user->referrals()->with('package'))->latest()->take(50)->get();
        $direct->each(fn (User $member): User => $member->setAttribute('level', 'direct'));

        $indirect = $matches(User::query()->with('package')->whereIn('referrer_id', $user->referrals()->select('id')))
            ->latest()->take(50)->get();
        $indirect->each(fn (User $member): User => $member->setAttribute('level', 'indirect'));

        $base = config('settings.referral.base_url') ?: config('app.url');

        return ApiResponse::success('Referrals retrieved successfully.', [
            'code' => $user->username,
            'link' => rtrim((string) $base, '/').'/auth/register?ref='.$user->username,
            'direct_count' => $user->referrals()->count(),
            'indirect_count' => User::query()->whereIn('referrer_id', $user->referrals()->select('id'))->count(),
            'earned_usd' => (float) $user->referral_bonus_balance,
            'direct' => ReferralResource::collection($direct),
            'indirect' => ReferralResource::collection($indirect),
        ]);
    }
}
