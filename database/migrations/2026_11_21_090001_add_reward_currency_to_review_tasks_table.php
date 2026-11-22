<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_tasks', function (Blueprint $table): void {
            $table->char('reward_currency', 3)->default('USD')->after('reserved_reward_minor');
        });

        // Existing task amounts were stored in their campaign currency. Keep
        // their meaning intact while new reservations snapshot plan currency.
        DB::table('review_tasks')
            ->whereIn('app_review_campaign_id', DB::table('app_review_campaigns')->select('id'))
            ->update([
                'reward_currency' => new Expression(
                    '(SELECT currency FROM app_review_campaigns WHERE app_review_campaigns.id = review_tasks.app_review_campaign_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('review_tasks', function (Blueprint $table): void {
            $table->dropColumn('reward_currency');
        });
    }
};
