<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialHandleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $handles = [
            ['handle_key' => 'facebook', 'name' => 'Facebook', 'channel_url' => 'https://www.facebook.com/tlstore', 'icon' => 'facebook', 'reward_amount_minor' => 50, 'currency' => 'USD', 'sort_order' => 1],
            ['handle_key' => 'instagram', 'name' => 'Instagram', 'channel_url' => 'https://www.instagram.com/tlstore/', 'icon' => 'instagram', 'reward_amount_minor' => 50, 'currency' => 'USD', 'sort_order' => 2],
            ['handle_key' => 'telegram', 'name' => 'Telegram', 'channel_url' => 'https://t.me/tlstore', 'icon' => 'telegram', 'reward_amount_minor' => 75, 'currency' => 'USD', 'sort_order' => 3],
            ['handle_key' => 'youtube', 'name' => 'YouTube', 'channel_url' => 'https://www.youtube.com/@tlstore', 'icon' => 'youtube', 'reward_amount_minor' => 75, 'currency' => 'USD', 'sort_order' => 4],
        ];

        foreach ($handles as &$handle) {
            $handle['is_active'] = true;
            $handle['created_at'] = $now;
            $handle['updated_at'] = $now;
        }
        unset($handle);

        DB::table('social_handles')->upsert(
            $handles,
            ['handle_key'],
            ['name', 'channel_url', 'icon', 'reward_amount_minor', 'currency', 'is_active', 'sort_order', 'updated_at'],
        );
    }
}
