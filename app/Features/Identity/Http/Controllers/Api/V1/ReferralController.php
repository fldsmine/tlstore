<?php

namespace App\Features\Identity\Http\Controllers\Api\V1;

use App\Models\ReferralCode;
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
                'code' => Str::upper(Str::random(10)),
                'status' => 'active',
            ]);
        }

        return response()->json(['data' => [
            'code' => $code->code,
            'attributed_signups' => $code->attributions()->count(),
            'qualified_signups' => $code->attributions()->where('status', 'qualified')->count(),
        ]]);
    }

    public function attributions(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->sentReferrals()
            ->with(['referredUser:id,username,created_at', 'rewards:id,referral_attribution_id,reward_type,amount_minor,currency,status,created_at'])
            ->latest('attributed_at')->paginate(30)]);
    }
}
