<?php

namespace Database\Seeders;

use App\Models\CallType;
use Illuminate\Database\Seeder;

class CallTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Rates are in USD (1 USD ≈ 1000 NGN). Claim per call = minutes × rate × claimable%.
        // Tuned so a single call claims a small naira amount, capped per type and rising by
        // call value: On-Net ~₦1–2, Broadband ~₦1.6–6, Off-Net ~₦1.8–9, International ~₦2.5–15.
        $types = [
            [
                'key' => 'on_net',
                'sort_order' => 0,
                'name' => 'On-Net',
                'description' => 'Calls to other Nextel numbers.',
                'rate_per_minute' => 0.00042,
                'claimable_percentage' => 60,
                'share_purchase_prompt_minutes' => 90,
                'min_minutes' => 4,
                'max_minutes' => 8,
                'min_per_batch' => 2,
                'max_per_batch' => 6,
                'color' => '#18443E',
            ],
            [
                'key' => 'off_net',
                'sort_order' => 1,
                'name' => 'Off-Net',
                'description' => 'Calls to other Nigerian networks.',
                'rate_per_minute' => 0.001,
                'claimable_percentage' => 45,
                'share_purchase_prompt_minutes' => 90,
                'min_minutes' => 4,
                'max_minutes' => 20,
                'min_per_batch' => 2,
                'max_per_batch' => 5,
                'color' => '#2F8F7E',
            ],
            [
                'key' => 'international',
                'sort_order' => 3,
                'name' => 'International',
                'description' => 'Calls to international destinations.',
                'rate_per_minute' => 0.00167,
                'claimable_percentage' => 30,
                'share_purchase_prompt_minutes' => 90,
                'min_minutes' => 5,
                'max_minutes' => 30,
                'min_per_batch' => 1,
                'max_per_batch' => 4,
                'color' => '#8B5CF6',
            ],
            [
                'key' => 'broadband',
                'sort_order' => 2,
                'name' => 'Broadband',
                'description' => 'Data and broadband usage minutes.',
                'rate_per_minute' => 0.0008,
                'claimable_percentage' => 50,
                'share_purchase_prompt_minutes' => 90,
                'min_minutes' => 4,
                'max_minutes' => 15,
                'min_per_batch' => 1,
                'max_per_batch' => 4,
                'color' => '#F59E0B',
            ],
        ];

        foreach ($types as $type) {
            CallType::query()->updateOrCreate(['key' => $type['key']], $type);
        }
    }
}
