<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppListingSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/app-listings.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read app listings at {$path}");
        }

        $model = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $now = now();
        $rows = array_map(static function (array $app) use ($now): array {
            return [
                'prototype_id' => $app['prototype_id'],
                'name' => $app['name'],
                'short_code' => $app['short_code'],
                'store' => $app['store'],
                'brand_color' => $app['brand_color'],
                'developer' => $app['developer'],
                'category' => $app['category'],
                'install_count_label' => $app['install_count_label'],
                'description' => $app['description'],
                'store_link' => $app['store_link'],
                'paid_amount' => $app['paid_amount'],
                'location' => $app['location'],
                'currency' => $app['currency'],
                'rating' => $app['rating'],
                'total_reviews' => $app['total_reviews'],
                'review_reward' => $app['review_reward'],
                'app_icon' => $app['app_icon'],
                'maximum_review' => $app['maximum_review'],
                'featured' => $app['featured'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $model['apps']);
		
        DB::table('apps')->upsert(
            $rows,
            ['prototype_id'],
            [
                'name',
                'short_code',
                'store',
                'brand_color',
                'developer',
                'category',
                'install_count_label',
                'description',
                'store_link',
                'paid_amount',
                'location',
                'currency',
                'rating',
                'total_reviews',
                'review_reward',
                'app_icon',
                'maximum_review',
                'featured',
                'updated_at',
            ]
        );
    }
}
