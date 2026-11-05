<?php

namespace App\Features\System\Http\Controllers\Api\V1;

use App\Models\AdShareEvent;
use App\Models\LedgerEntry;
use App\Models\ReviewSubmission;
use App\Models\SystemSetting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class AboutController
{
    public function show(): JsonResponse
    {
        $settings = SystemSetting::query()
            ->where('is_public', true)
            ->where('setting_key', 'like', 'about.%')
            ->get(['setting_key', 'value'])
            ->mapWithKeys(fn (SystemSetting $setting) => [substr($setting->setting_key, 6) => $setting->value]);

        $approvedReviews = ReviewSubmission::query()->where('status', 'approved')->count();
        $approvedAdShares = AdShareEvent::query()->where('status', 'validated')->count();
        $paidRewardCount = LedgerEntry::query()
            ->where('direction', 'credit')
            ->where('status', 'posted')
            ->whereIn('entry_type', [
                'review_reward', 'ad_share_reward', 'referral_subscription_reward',
                'referral_task_reward', 'subscription_bonus', 'social_follow_reward',
            ])
            ->count();

        return response()->json([
            'data' => [
                'content' => $settings,
                'stats' => [
                    'members' => User::query()->where('role', 'reviewer')->where('status', 'active')->count(),
                    'approved_tasks' => $approvedReviews + $approvedAdShares,
                    'reward_payouts' => $paidRewardCount,
                    'testimonials' => Testimonial::query()->where('status', 'approved')->count(),
                ],
            ],
        ]);
    }
}
