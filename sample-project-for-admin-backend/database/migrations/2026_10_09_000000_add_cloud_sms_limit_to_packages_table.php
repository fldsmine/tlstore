<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Daily sponsored-SMS claim limit for the package (the base quota). 0 = no cap,
            // matching the cloud_daily_earning_cap convention — the seeded packages set real
            // values, and cloud storage packs add extra_sms on top of this base.
            if (! Schema::hasColumn('packages', 'cloud_sms_limit')) {
                $table->unsignedInteger('cloud_sms_limit')->default(0)->after('cloud_contact_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'cloud_sms_limit')) {
                $table->dropColumn('cloud_sms_limit');
            }
        });
    }
};
