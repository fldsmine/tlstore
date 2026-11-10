<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdListingSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/ad-listings.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read ad listings at {$path}");
        }

        $ads = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $now = now();
        $rows = array_map(static function (array $ad) use ($now): array {
            return [
                'campaign_key' => $ad['campaign_key'],
                'title' => $ad['title'],
                'type' => $ad['type'] ?? 'others',
                'description' => $ad['description'],
                'share_message' => $ad['share_message'],
                'owner_id' => $ad['owner_id'],
                'creative_path' => $ad['creative_path'],
                'destination_url' => $ad['destination_url'],
                'paid_amount' => $ad['paid_amount'],
                'location' => $ad['location'],
                'currency' => $ad['currency'],
                'total_share' => $ad['total_share'],
                'share_reward' => $ad['share_reward'],
                'maximum_share' => $ad['maximum_share'],
                'status' => $ad['status'],
                'starts_at' => $ad['starts_at'],
                'ends_at' => $ad['ends_at'],
                'featured' => $ad['featured'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $ads);

        DB::table('ads')->upsert(
            $rows,
            ['campaign_key'],
            [
                'title',
                'type',
                'description',
                'share_message',
                'owner_id',
                'creative_path',
                'destination_url',
                'paid_amount',
                'location',
                'currency',
                'total_share',
                'share_reward',
                'maximum_share',
                'status',
                'starts_at',
                'ends_at',
                'featured',
                'updated_at',
            ]
        );
    }
}
