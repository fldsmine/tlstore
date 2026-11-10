<?php

namespace App\Console\Commands;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\Ad;
use App\Models\App as AppListing;
use App\Models\AppReviewCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateDailyDemoListings extends Command
{
    protected $signature = 'demo:generate-listings';

    protected $description = 'Generate three app review and four ad share listings for today in non-production environments';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('App listings can only be generated outside production.');
            return self::FAILURE;
        }

        $day = now()->format('Ymd');
        $prefix = 'sample-daily-'.$day;
        $existingApps = AppReviewCampaign::query()->where('campaign_key', 'like', $prefix.'-app-%')->count();
        $existingAds = Ad::query()->where('campaign_key', 'like', $prefix.'-ad-%')->count();
        
		if ($existingApps === 10 && $existingAds === 12) {
            $this->info('Today’s sample listings have already been generated.');
            return self::SUCCESS;
        }
		
        if ($existingApps > 0 || $existingAds > 0) {
            $this->warn('A partial set exists for today; missing listings will be added.');
        }

        $now = now();
        $endsAt = $now->copy()->addDays(7);

		$appTemplates = [
			[
				'name' => 'Fit Tracker',
				'store' => 'Google Play',
				'category' => 'Health & Fitness',
				'developer' => 'Start Labs',
				'color' => '#157F3D',
				'location' => 'NG',
				'currency' => 'NGN',
				'reward' => 100,
				'reviews' => 40,
				'paid' => 4000,
			],
			[
				'name' => 'Budget Planner',
				'store' => 'Samsung Galaxy Store',
				'category' => 'Finance',
				'developer' => 'Jam Works',
				'color' => '#C8102E',
				'location' => 'GH',
				'currency' => 'GHS',
				'reward' => 2,
				'reviews' => 30,
				'paid' => 60,
			],
			[
				'name' => 'Photo Studio',
				'store' => 'Apple App Store',
				'category' => 'Photo & Video',
				'developer' => 'Rock Studio',
				'color' => '#314159',
				'location' => null,
				'currency' => 'USD',
				'reward' => 0.15,
				'reviews' => 25,
				'paid' => 3.75,
			],
			[
				'name' => 'Learn English',
				'store' => 'Google Play',
				'category' => 'Education',
				'developer' => 'Bright Future',
				'color' => '#2563EB',
				'location' => null,
				'currency' => 'NGN',
				'reward' => 80,
				'reviews' => 50,
				'paid' => 4000,
			],
			[
				'name' => 'Food Recipe',
				'store' => 'Apple App Store',
				'category' => 'Food & Drink',
				'developer' => 'Kitchen Hub',
				'color' => '#EA580C',
				'location' => 'US',
				'currency' => 'USD',
				'reward' => 0.20,
				'reviews' => 30,
				'paid' => 6,
			],
			[
				'name' => 'Travel Planner',
				'store' => 'Google Play',
				'category' => 'Travel & Local',
				'developer' => 'Explore Tech',
				'color' => '#0891B2',
				'location' => null,
				'currency' => 'GHS',
				'reward' => 1.5,
				'reviews' => 40,
				'paid' => 60,
			],
			[
				'name' => 'Task Manager',
				'store' => 'Samsung Galaxy Store',
				'category' => 'Productivity',
				'developer' => 'Work Smart',
				'color' => '#7C3AED',
				'location' => 'NG',
				'currency' => 'NGN',
				'reward' => 120,
				'reviews' => 35,
				'paid' => 4200,
			],
			[
				'name' => 'Music Player',
				'store' => 'Google Play',
				'category' => 'Music & Audio',
				'developer' => 'Sound Wave',
				'color' => '#DB2777',
				'location' => null,
				'currency' => 'USD',
				'reward' => 0.10,
				'reviews' => 50,
				'paid' => 5,
			],
			[
				'name' => 'Fitness Coach',
				'store' => 'Apple App Store',
				'category' => 'Health & Fitness',
				'developer' => 'Active Life',
				'color' => '#16A34A',
				'location' => 'NG',
				'currency' => 'NGN',
				'reward' => 150,
				'reviews' => 30,
				'paid' => 4500,
			],
			[
				'name' => 'Expense Tracker',
				'store' => 'Google Play',
				'category' => 'Finance',
				'developer' => 'Money Wise',
				'color' => '#CA8A04',
				'location' => 'US',
				'currency' => 'USD',
				'reward' => 0.25,
				'reviews' => 40,
				'paid' => 10,
			],
		];


		$adTemplates = [
			[
				'type' => 'Facebook',
				'title' => 'Share a wellness tip',
				'location' => null,
				'currency' => 'NGN',
				'reward' => 50,
				'shares' => 40,
				'paid' => 2000,
			],
			[
				'type' => 'Instagram',
				'title' => 'Discover a local creator',
				'location' => 'GH',
				'currency' => 'GHS',
				'reward' => 1,
				'shares' => 30,
				'paid' => 30,
			],
			[
				'type' => 'Telegram',
				'title' => 'Share a learning resource',
				'location' => null,
				'currency' => 'USD',
				'reward' => 0.10,
				'shares' => 25,
				'paid' => 2.50,
			],
			[
				'type' => 'YouTube',
				'title' => 'Share a product video',
				'location' => 'US',
				'currency' => 'USD',
				'reward' => 0.12,
				'shares' => 20,
				'paid' => 2.40,
			],
			[
				'type' => 'Facebook',
				'title' => 'Promote a fitness challenge',
				'location' => null,
				'currency' => 'NGN',
				'reward' => 75,
				'shares' => 50,
				'paid' => 3750,
			],
			[
				'type' => 'Instagram',
				'title' => 'Discover a fashion brand',
				'location' => 'GH',
				'currency' => 'GHS',
				'reward' => 1.5,
				'shares' => 40,
				'paid' => 60,
			],
			[
				'type' => 'Telegram',
				'title' => 'Share a technology update',
				'location' => 'NG',
				'currency' => 'NGN',
				'reward' => 60,
				'shares' => 35,
				'paid' => 2100,
			],
			[
				'type' => 'YouTube',
				'title' => 'Share an educational video',
				'location' => null,
				'currency' => 'USD',
				'reward' => 0.15,
				'shares' => 30,
				'paid' => 4.50,
			],
			[
				'type' => 'Facebook',
				'title' => 'Share a business opportunity',
				'location' => 'US',
				'currency' => 'USD',
				'reward' => 0.20,
				'shares' => 25,
				'paid' => 5,
			],
			[
				'type' => 'Instagram',
				'title' => 'Promote a food discovery',
				'location' => 'NG',
				'currency' => 'NGN',
				'reward' => 100,
				'shares' => 30,
				'paid' => 3000,
			],
			[
				'type' => 'Telegram',
				'title' => 'Share a community event',
				'location' => 'GH',
				'currency' => 'GHS',
				'reward' => 2,
				'shares' => 25,
				'paid' => 50,
			],
			[
				'type' => 'YouTube',
				'title' => 'Discover a new product',
				'location' => 'US',
				'currency' => 'USD',
				'reward' => 0.18,
				'shares' => 40,
				'paid' => 7.20,
			],
		];

        DB::transaction(function () use ($appTemplates, $adTemplates, $day, $prefix, $now, $endsAt): void {
            $prototypeId = ((int) AppListing::query()->max('prototype_id')) + 1;
            foreach ($appTemplates as $index => $template) {
                $campaignKey = $prefix.'-app-'.($index + 1);
                if (AppReviewCampaign::query()->where('campaign_key', $campaignKey)->exists()) continue;
                $app = AppListing::query()->create([
                    'prototype_id' => $prototypeId++,
                    'name' => $template['name'].' '.$day,
                    'short_code' => 'D'.substr($day, 2).($index + 1),
                    'store' => $template['store'],
                    'brand_color' => $template['color'],
                    'developer' => $template['developer'],
                    'category' => $template['category'],
                    'install_count_label' => '1K+',
                    'description' => 'Listing for validating the app review flow.',
                    'store_link' => 'https://play.google.com/store/demo-app',
                    'paid_amount' => $template['paid'],
                    'location' => $template['location'],
                    'currency' => $template['currency'],
                    'rating' => 4.5,
                    'total_reviews' => 128,
                    'review_reward' => $template['reward'],
                    'app_icon' => 'images/app-image-'.(($index % 8) + 1).'.jpg',
                    'maximum_review' => $template['reviews'],
                    'featured' => false,
                    'status' => 'active',
                ]);
				
                AppReviewCampaign::query()->create([
                    'campaign_key' => $campaignKey,
                    'app_id' => $app->id,
                    'owner_id' => null,
                    'paid_amount_minor' => CurrencyMinorUnits::toMinor($template['paid'], $template['currency']),
                    'currency' => $template['currency'],
                    'location' => $template['location'],
                    'review_reward_minor' => CurrencyMinorUnits::toMinor($template['reward'], $template['currency']),
                    'maximum_review' => $template['reviews'],
                    'reserved_reviews' => 0,
                    'completed_reviews' => 0,
                    'status' => 'active',
                    'starts_at' => $now,
                    'ends_at' => $endsAt,
                ]);
            }

            foreach ($adTemplates as $index => $template) {
                $campaignKey = $prefix.'-ad-'.($index + 1);
                if (Ad::query()->where('campaign_key', $campaignKey)->exists()) continue;
                Ad::query()->create([
                    'campaign_key' => $campaignKey,
                    'title' => $template['title'],
                    'type' => $template['type'],
                    'description' => 'Daily ad listing for validating the public share task flow.',
                    'share_message' => 'Check out this campaign: ',
                    'owner_id' => null,
                    'creative_path' => 'images/app-image-'.(($index % 8) + 1).'.jpg',
                    'destination_url' => 'https://example.com/demo/'.Str::slug($campaignKey),
                    'paid_amount' => $template['paid'],
                    'location' => $template['location'],
                    'currency' => $template['currency'],
                    'total_share' => 0,
                    'reserved_share' => 0,
                    'share_reward' => $template['reward'],
                    'maximum_share' => $template['shares'],
                    'status' => 'active',
                    'starts_at' => $now,
                    'ends_at' => $endsAt,
                    'featured' => false,
                ]);
            }
        });

        $this->info('Generated ten app review listings and twelve ad listings for '.$day.'.');
        return self::SUCCESS;
    }
}
