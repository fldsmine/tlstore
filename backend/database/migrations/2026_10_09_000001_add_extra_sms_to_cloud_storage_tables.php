<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A storage pack now grants extra contact slots AND extra daily SMS access. The plan
        // carries the grant; the redeemed top-up snapshots it (mirroring extra_contacts).
        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_storage_plans', 'extra_sms')) {
                $table->unsignedInteger('extra_sms')->default(0)->after('extra_contacts');
            }
        });

        Schema::table('cloud_storage_top_ups', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_storage_top_ups', 'extra_sms')) {
                $table->unsignedInteger('extra_sms')->default(0)->after('extra_contacts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cloud_storage_top_ups', function (Blueprint $table) {
            if (Schema::hasColumn('cloud_storage_top_ups', 'extra_sms')) {
                $table->dropColumn('extra_sms');
            }
        });

        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (Schema::hasColumn('cloud_storage_plans', 'extra_sms')) {
                $table->dropColumn('extra_sms');
            }
        });
    }
};
