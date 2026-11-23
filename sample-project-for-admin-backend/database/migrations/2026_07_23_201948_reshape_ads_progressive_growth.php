<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->decimal('growth_percent', 5, 2)->default(0)->after('shares');   // e.g. 5.00 = +5% per interval
            $table->unsignedSmallInteger('growth_interval_hours')->default(2)->after('growth_percent');
            $table->timestamp('last_grown_at')->nullable()->after('growth_interval_hours');
            $table->decimal('base_reward', 36, 6)->default(0)->after('reward');      // reset target for the reward
        });

        // Seed base_reward from the current reward so a reset restores today's value.
        DB::table('ads')->update(['base_reward' => DB::raw('reward')]);

        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['daily_impression_growth', 'daily_view_growth']);
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->unsignedInteger('daily_impression_growth')->default(0);
            $table->unsignedInteger('daily_view_growth')->default(0);
            $table->dropColumn(['growth_percent', 'growth_interval_hours', 'last_grown_at', 'base_reward']);
        });
    }
};
