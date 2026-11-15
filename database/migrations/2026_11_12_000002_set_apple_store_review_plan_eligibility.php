<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')
            ->where('plan_key', 'review-apple-store-4m')
            ->update([
                'eligible_app_store' => 'Apple App Store',
                'eligible_app_category' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')
            ->where('plan_key', 'review-apple-store-4m')
            ->update([
                'eligible_app_store' => null,
                'eligible_app_category' => 'Apple',
                'updated_at' => now(),
            ]);
    }
};
