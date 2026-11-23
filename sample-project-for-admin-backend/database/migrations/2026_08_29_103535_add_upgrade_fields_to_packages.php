<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Package upgrades: a user on a lower-tier package upgrades to a higher one by redeeming a
     * vendor-minted UPGRADE coupon. Upgrade pricing + referral bonuses live on the TARGET package
     * (all USD, foreign nullable → falls back to the Nigerian value via RegionPricing), distinct
     * from the activation price/bonuses. Replaces the unused single `upgrade_referral_bonus` column
     * with a direct + indirect split.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'upgrade_price')) {
                $table->decimal('upgrade_price', 36, 6)->default(0)->after('indirect_referral_bonus');           // customer cost to upgrade TO this package
            }
            if (! Schema::hasColumn('packages', 'upgrade_agent_price')) {
                $table->decimal('upgrade_agent_price', 36, 6)->default(0)->after('upgrade_price');                // vendor cost to mint an upgrade coupon
            }
            if (! Schema::hasColumn('packages', 'foreign_upgrade_price')) {
                $table->decimal('foreign_upgrade_price', 36, 6)->nullable()->after('upgrade_agent_price');
            }
            if (! Schema::hasColumn('packages', 'foreign_upgrade_agent_price')) {
                $table->decimal('foreign_upgrade_agent_price', 36, 6)->nullable()->after('foreign_upgrade_price');
            }
            if (! Schema::hasColumn('packages', 'upgrade_direct_referral_bonus')) {
                $table->decimal('upgrade_direct_referral_bonus', 36, 6)->default(0)->after('foreign_upgrade_agent_price');   // direct upline earns on upgrade
            }
            if (! Schema::hasColumn('packages', 'upgrade_indirect_referral_bonus')) {
                $table->decimal('upgrade_indirect_referral_bonus', 36, 6)->default(0)->after('upgrade_direct_referral_bonus'); // 2nd-level upline earns on upgrade
            }
        });

        // The old single-value column is superseded by the direct/indirect split above.
        if (Schema::hasColumn('packages', 'upgrade_referral_bonus')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('upgrade_referral_bonus');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('packages', 'upgrade_referral_bonus')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->decimal('upgrade_referral_bonus', 36, 6)->default(0)->after('indirect_referral_bonus');
            });
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'upgrade_price',
                'upgrade_agent_price',
                'foreign_upgrade_price',
                'foreign_upgrade_agent_price',
                'upgrade_direct_referral_bonus',
                'upgrade_indirect_referral_bonus',
            ]);
        });
    }
};
