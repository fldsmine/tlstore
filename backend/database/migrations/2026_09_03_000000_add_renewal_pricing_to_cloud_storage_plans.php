<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renewable cloud storage plans carry a renewal price distinct from their activation (token)
     * price. A plan is "renewable" when renewal_token_price > 0. Foreign columns are nullable and
     * fall back to the Nigerian value (see RegionPricing).
     */
    public function up(): void
    {
        Schema::table('cloud_storage_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('cloud_storage_plans', 'renewal_token_price')) {
                $table->decimal('renewal_token_price', 36, 6)->default(0)->after('foreign_agent_price');
            }
            if (! Schema::hasColumn('cloud_storage_plans', 'renewal_agent_price')) {
                $table->decimal('renewal_agent_price', 36, 6)->default(0)->after('renewal_token_price');
            }
            if (! Schema::hasColumn('cloud_storage_plans', 'foreign_renewal_token_price')) {
                $table->decimal('foreign_renewal_token_price', 36, 6)->nullable()->after('renewal_agent_price');
            }
            if (! Schema::hasColumn('cloud_storage_plans', 'foreign_renewal_agent_price')) {
                $table->decimal('foreign_renewal_agent_price', 36, 6)->nullable()->after('foreign_renewal_token_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cloud_storage_plans', function (Blueprint $table): void {
            foreach ([
                'renewal_token_price',
                'renewal_agent_price',
                'foreign_renewal_token_price',
                'foreign_renewal_agent_price',
            ] as $column) {
                if (Schema::hasColumn('cloud_storage_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
