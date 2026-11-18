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
        $existingAds = Ad::query()->where('campaign_key', 'like', $prefix.'-ad-%')->count();
        
		if ( $existingAds === 2) {
            $this->info('Today’s sample listings have already been generated.');
            return self::SUCCESS;
        }
		
        if ( $existingAds > 0) {
            $this->warn('A partial set exists for today; missing listings will be added.');
        }

        $now = now();
        $endsAt = $now->copy()->addDays(7);


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

        DB::transaction(function () use ($adTemplates, $day, $prefix, $now, $endsAt): void {

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

        $this->info('Generated 2 ad listings for '.$day.'.');
        return self::SUCCESS;
    }
}
