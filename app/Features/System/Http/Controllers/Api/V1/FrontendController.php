<?php

namespace App\Features\System\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\Cache;
use App\Services\PublicFrontService;
use Illuminate\Http\JsonResponse;
use App\Models\ReviewSubmission;
use App\Models\SystemSetting;
use App\Models\AdShareEvent;
use Illuminate\Http\Request;
use App\Models\Testimonial;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Faq;

final class FrontendController
{
	private const CACHE_KEY = 'api:frontend:v2';
	private const CACHE_TTL = 10; // minutes

    public function about(): JsonResponse
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

    public function frontend(Request $request, PublicFrontService $sports)
    {
		//$data = Cache::get(self::CACHE_KEY);

		//if (!$data) {
			
			$data = $sports->getSportlight();
			
			$settings = SystemSetting::query()
				->where('is_public', true)
				->where('setting_key', 'like', 'about.%')
				->get(['setting_key', 'value'])
				->mapWithKeys(fn (SystemSetting $setting) => [substr($setting->setting_key, 6) => $setting->value]);

			$data['banner'] = $this->getBannerData($settings);
			$data['how_it_works'] = $this->howItWorks();
			$data['contact_info'] = $this->getContactInfo($settings);
			$data['features'] = $settings['features'];
			$data['platform_stats'] = $this->getPlatformStats();
			$data['leaderboard'] = $this->getLeaderboard();
			$data['faqs'] = $this->getFaqs();
			$data['support_link'] = [
				getValue($settings, 'supports.link1', 'https://t.me/tlstore_general'),
				getValue($settings, 'supports.link2', 'https://t.me/tlstore_help'),
			];
			$data['footer'] = [
				'copyright' => $settings['footer_copyright'],
				'note' => $settings['footer_note'],
			];
			
		//	Cache::put(self::CACHE_KEY, $data);
		//}

        return response()->json(['data' => $data]);

    }

    private function getPlatformStats()
    {

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
	
		return [
            [
                'key' => 'stats_members',
                'label' => 'Community Members',
                'value' => number_format(User::where('status', UserStatus::Active->value)->count()),
                'icon' => 'users',
            ],
            [
                'key' => 'stats_approved',
                'label' => 'Approved Tasks',
                'value' => number_format($approvedReviews+$approvedAdShares),
                'icon' => 'image',
            ],
            [
                'key' => 'stats_rewards',
                'label' => 'Rewards Paid',
                'value' => '$' . number_format($paidRewardCount),
                'icon' => 'dollar',
            ],
            [
                'key' => 'countries',
                'label' => 'Countries',
                'value' => (string)User::distinct('country_code')->whereNotNull('country_code')->count(),
                'icon' => 'globe',
            ],
        ];
    }

	private function howItWorks()
	{
		return [
			[
				'number' => '1',
				'icon' => 'person_add_alt_1_rounded',
				'title' => 'Create your account',
				'description' => 'Register with your country and set up your TLStore profile.',
				'color' => 'primary',
				'hasline' => true,
			],
			[
				'number' => '2',
				'icon' => 'workspace_premium_rounded',
				'title' => 'Choose a package',
				'description' => 'Join the package that gives you access to app reviews or sponsored sharing.',
				'color' => 'secondary',
				'hasline' => true,
			],
			[
				'number' => '3',
				'icon' => 'task_alt_rounded',
				'title' => 'Complete a task',
				'description' => 'Review an app or share a sponsored post, then submit your proof when required.',
				'color' => 'tertiary',
				'hasline' => true,
			],
			[
				'number' => '4',
				'icon' => 'account_balance_wallet_rounded',
				'title' => 'Get approved and earn',
				'description' => 'Approved work is credited to your wallet. Track your earnings and request a withdrawal.',
				'color' => 'primary',
				'hasline' => false,
			],
		];
	}
	
	private function getLeaderboard(string $period = 'all_time')
	{
		$startDate = match ($period) {
			'weekly' => now()->subWeek(),
			'monthly' => now()->subMonth(),
			default => null,
		};

		$reviewTasksCount = function ($query) use ($startDate) {
			if ($startDate) {
				$query->where('created_at', '>=', $startDate);
			}
		};

		$adShareTasksCount = function ($query) use ($startDate) {
			if ($startDate) {
				$query->where('created_at', '>=', $startDate);
			}
		};

		return User::query()
			->where('status', UserStatus::Active->value)
			->where('is_banned', false)
			->withCount([
				'reviewTasks as total_review' => $reviewTasksCount,
				'adShareTasks as total_share' => $adShareTasksCount,
			])
			->selectRaw('
				(
					(SELECT COUNT(*) FROM review_tasks
					 WHERE review_tasks.reviewer_id = users.id
					 ' . ($startDate ? 'AND review_tasks.created_at >= ?' : '') . ')
					+
					(SELECT COUNT(*) FROM ad_share_tasks
					 WHERE ad_share_tasks.user_id = users.id
					 ' . ($startDate ? 'AND ad_share_tasks.created_at >= ?' : '') . ')
				) AS total_score
			', $startDate ? [$startDate, $startDate] : [])
			->orderByDesc('total_score')
			->limit(10)
			->get()
			->map(function ($user, $index) {
				return [
					'rank' => $index + 1,
					'user' => [
						'id' => $user->id,
						'username' => $user->username,
						'full_name' => $user->full_name,
						'avatar' => $user->avatar,
						'country_code' => $user->country_code,
						'has_addon' => $user->has_addon,
					],
					'total_review' => (int) $user->total_review,
					'total_share' => (int) $user->total_share,
					'total_score' => (int) $user->total_score,
				];
			});
	}

	
	private function getBannerData(array|\Illuminate\Support\Collection $settings): array
	{
		return [
			'title' => getValue($settings, 'hero_tagline', 'Welcome to Trust lens store'),
			'subtitle' => getValue($settings, 'what_we_do_body', 'Built for people who make apps better'),
			'description' => getValue(
				$settings,
				'mission_body',
				'A community marketplace for app feedback and sponsored sharing.'
			),
			'image' => getValue(
				$settings,
				'hero_image_url',
				'https://picsum.photos/seed/701/600/800'
			),
			'cta_text' => 'Get Started',
			'cta_link' => '/register',
		];
	}

	private function getContactInfo(array|\Illuminate\Support\Collection $settings): array
	{
        return [
            'email' => getValue($settings, 'contact_email', 'support@tlstore.org'),
            'phone' => getValue($settings, 'contact_phone', '+1 394 567 8900'),
            'address' => getValue($settings, 'contact_address', '123 AI Street, Tech City'),
            'social_links' => [
                'facebook' => getValue($settings, 'social_links.facebook', 'https://facebook.com'),
                'twitter' => getValue($settings, 'social_links.twitter' ,''),
                'instagram' => getValue($settings, 'social_links.instagram' ,''),
                'tiktok' => getValue($settings, 'social_links.tiktok' ,''),
                'telegram' => getValue($settings, 'social_links.telegram' ,'https://telegram.com'),
            ],
        ];
	}

    private function getFaqs()
    {
		return Faq::query()->active()->ordered()
			->get(['id', 'question', 'answer', 'category'])
			->groupBy('category')
			->map(fn ($faqs) => $faqs->map(fn ($faq) => [
				'id' => $faq->id,
				'question' => $faq->question,
				'answer' => $faq->answer,
			]));
    }

}
