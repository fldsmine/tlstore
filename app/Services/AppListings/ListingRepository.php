<?php

declare(strict_types=1);

namespace App\Services\AppListings;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\App as StoreApp;
use App\Models\AppReviewCampaign;
use App\Models\ReviewTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Stores generated app listings in the same tables read by the marketplace API. */
class ListingRepository
{
    /** @return array<int, array{name:string}> */
    public function existing(): array
    {
        return StoreApp::query()->orderBy('id')->pluck('name')
            ->map(static fn (string $name): array => ['name' => $name])
            ->all();
    }

    public function publishedTemplateVersion(): ?ReviewTemplateVersion
    {
        return ReviewTemplateVersion::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereHas('questions')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Persist an app, its store screenshots, and the active campaign that
     * makes it visible and claimable through the current marketplace API.
     *
     * @param  array<int, array<string, mixed>>  $listings
     * @return array<int, array{app_id:int,campaign_id:int,name:string}>
     */
    public function store(array $listings, ReviewTemplateVersion $templateVersion): array
    {
        if ($listings === []) {
            return [];
        }

        $created = DB::transaction(function () use ($listings, $templateVersion): array {
            $template = ReviewTemplateVersion::query()
                ->whereKey($templateVersion->id)
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->whereHas('questions')
                ->first();

            if (! $template) {
                throw new RuntimeException('The selected published review-question template is no longer available.');
            }

            $result = [];
            foreach ($listings as $listing) {
                $currency = strtoupper((string) $listing['currency']);
                $rewardMinor = CurrencyMinorUnits::toMinor((string) $listing['review_reward'], $currency);
                $maximum = (int) $listing['maximum_review'];
                $paidAmountMinor = CurrencyMinorUnits::toMinor((string) $listing['paid_amount'], $currency);

                if ($rewardMinor < 1 || $maximum < 1 || $paidAmountMinor < $rewardMinor * $maximum) {
                    throw new RuntimeException('Generated reward pool does not cover its review maximum for '.$listing['name'].'.');
                }

                $app = StoreApp::query()->create([
                    'prototype_id' => null,
                    'name' => $listing['name'],
                    'short_code' => $listing['short_code'],
                    'store' => $listing['store'],
                    'brand_color' => $listing['brand_color'],
                    'developer' => $listing['developer'],
                    'category' => $listing['category'],
                    'install_count_label' => $listing['install_count_label'],
                    'description' => $listing['description'],
                    'store_link' => $listing['store_link'],
                    'paid_amount' => $listing['paid_amount'],
                    'location' => $listing['location'],
                    'currency' => $currency,
                    'rating' => $listing['rating'],
                    'total_reviews' => $listing['total_reviews'],
                    'review_reward' => $listing['review_reward'],
                    'app_icon' => $listing['app_icon'],
                    'maximum_review' => $maximum,
                    'featured' => false,
                    'status' => 'active',
                ]);

                foreach ((array) ($listing['screenshots'] ?? []) as $screenshot) {
                    $app->screenshots()->create([
                        'position' => (int) $screenshot['position'],
                        'source_type' => $screenshot['source_type'] ?? 'image',
                        'storage_disk' => $screenshot['storage_disk'] ?? null,
                        'storage_path' => $screenshot['storage_path'] ?? null,
                        'illustration_index' => $screenshot['illustration_index'] ?? null,
                        'alt_text' => $screenshot['alt_text'] ?? ($app->name.' screenshot'),
                    ]);
                }

                $campaign = AppReviewCampaign::query()->create([
                    'campaign_key' => 'ai-review-'.Str::lower(Str::random(24)),
                    'app_id' => $app->id,
                    'review_template_version_id' => $template->id,
                    'owner_id' => null,
                    'paid_amount_minor' => $paidAmountMinor,
                    'currency' => $currency,
                    'location' => $listing['location'],
                    'review_reward_minor' => $rewardMinor,
                    'maximum_review' => $maximum,
                    'reserved_reviews' => 0,
                    'completed_reviews' => 0,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => null,
                ]);

                $result[] = [
                    'app_id' => $app->id,
                    'campaign_id' => $campaign->id,
                    'name' => $app->name,
                ];
            }

            return $result;
        });

        return $created;
    }
}
