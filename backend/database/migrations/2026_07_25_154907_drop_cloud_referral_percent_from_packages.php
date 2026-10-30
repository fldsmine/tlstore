<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'cloud_referral_percent')) {
                $table->dropColumn('cloud_referral_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'cloud_referral_percent')) {
                $table->decimal('cloud_referral_percent', 5, 2)->default(0)->after('indirect_referral_bonus');
            }
        });
    }
};
