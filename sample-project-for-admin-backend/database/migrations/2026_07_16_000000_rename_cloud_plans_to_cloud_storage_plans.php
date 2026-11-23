<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Cloud plan" and "contact pack" both described the same thing badly: a Cloud STORAGE PLAN —
     * how many sponsor contacts a user may keep saved. The plan is `cloud_storage_plans`; a user's
     * redeemed copy of one is `cloud_storage_top_ups`, mirroring minute_top_ups for minute plans.
     */
    public function up(): void
    {
        if (Schema::hasTable('cloud_plans')) {
            Schema::rename('cloud_plans', 'cloud_storage_plans');
        }

        if (Schema::hasTable('cloud_contact_packs')) {
            Schema::rename('cloud_contact_packs', 'cloud_storage_top_ups');
        }

        foreach (['coupons', 'coupon_batches', 'cloud_storage_top_ups'] as $table) {
            if (Schema::hasColumn($table, 'cloud_plan_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->renameColumn('cloud_plan_id', 'cloud_storage_plan_id'));
            }
        }
    }

    public function down(): void
    {
        foreach (['coupons', 'coupon_batches', 'cloud_storage_top_ups'] as $table) {
            if (Schema::hasColumn($table, 'cloud_storage_plan_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->renameColumn('cloud_storage_plan_id', 'cloud_plan_id'));
            }
        }

        if (Schema::hasTable('cloud_storage_top_ups')) {
            Schema::rename('cloud_storage_top_ups', 'cloud_contact_packs');
        }

        if (Schema::hasTable('cloud_storage_plans')) {
            Schema::rename('cloud_storage_plans', 'cloud_plans');
        }
    }
};
