<?php

namespace App\Services;

use App\Application\Actions\Marketplace\ListEligibleListings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\PaymentOption;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class PublicFrontService
{
	private const CACHE_KEY = 'api:spotlight:v2';
	private const CACHE_TTL = 60; // minutes

    public function getSportlight(): array
    {
		Cache::forget(self::CACHE_KEY);
		$data = Cache::remember(
			self::CACHE_KEY,
			now()->addMinutes(self::CACHE_TTL),
			function () {
				$listings = app(ListEligibleListings::class);
                return [
				'products' => $listings->app_sport(10)->getCollection()
                    ->map(fn ($campaign) => [
                        'id' => $campaign->app->id,
                        'campaign_id' => $campaign->id,
                        'featured' => (bool) $campaign->app->featured,
                        'title' => $campaign->app->store,
                        'description' => $campaign->app->description,
                        'price' => (float) $campaign->app->review_reward,
                        'currency' => $campaign->app->currency ?? 'NGN',
                        'name' => $campaign->app->name,
                        'rating' => $campaign->app->rating,
                        'total_reviews' => $campaign->app->total_reviews,
                        'location' => $campaign->location,
                        'cover_image' => \App\Application\Support\Media::url(
                            $campaign->app->app_icon
                        ),
                    ])
					->values()->all(),
				'ads' => $listings->featuredAds(10)->map(fn ($ad) => [
					'id' => $ad->id,
					'featured' => (bool) $ad->featured,
					'title' => $ad->title,
					'description' => $ad->description,
					'share_message' => $ad->share_message,
					'currency' => $ad->currency,
					'price' => (float) $ad->share_reward,
					'currency_minor_units' => $ad->currency_minor_units,
					'location' => $ad->location,
					'destination_url' => $ad->destination_url,
					'creative_path' => \App\Application\Support\Media::url($ad->creative_path),
				])->values()->all(),

                    'testimonies' => Testimonial::query()
						->join('users', 'users.id', '=', 'testimonials.user_id')
						->where('testimonials.status', 'approved')->where('testimonials.is_featured', true)
						->select([
							'testimonials.id','testimonials.content', 'testimonials.rating', 'users.avatar', 'users.full_name',
						])
						->take(6)
						->get()
						->map(fn ($t) => [
							'id' => $t->id,
							'content' => $t->content,
							'name' => $t->full_name,
							'avatar' => \App\Application\Support\Media::url($t->avatar),
							'rating' => (int) $t->rating,
						])->values()->all(),

                    'pay_option' => PaymentOption::select(['id', 'name', 'currency', 'symbol', 'method_code', 'gateway_alias', 
							   'percent_charge', 'fixed_charge', 'rate', 'description', 'logo', 'addresses', 'status'])->active()
						->orderBy('order')
						->get()
						->map(fn ($o) => array_merge(
							$o->only(['id', 'name', 'currency', 'symbol', 'method_code', 'gateway_alias',
							  'percent_charge', 'fixed_charge', 'rate', 'description', 'addresses', 'status']),
							['logo' => \App\Application\Support\Media::url($o->logo)],
						))->values()->all(),

				];
			}
		);

        return $data;
    }
	
	public function clearSportlight(){
		Cache::forget(self::CACHE_KEY);
	}


}