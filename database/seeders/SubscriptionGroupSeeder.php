<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionGroupSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        DB::table('subscription_groups')->upsert([
            ['group_key' => 'group_a', 'name' => 'ADS SHARE', 'description' => 'Packages for sharing sponsored ads.', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['group_key' => 'group_b', 'name' => 'ANDROID REVIEW', 'description' => 'Packages for reviewing Google Play and Samsung Galaxy Store apps.', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['group_key' => 'group_c', 'name' => 'IOS REVIEW', 'description' => 'Packages for reviewing Apple category apps.', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['group_key'], ['name', 'description', 'sort_order', 'is_active', 'updated_at']);
    }
}
