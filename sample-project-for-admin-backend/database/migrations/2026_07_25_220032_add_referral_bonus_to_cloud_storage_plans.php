<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            // USD Connect Bonus paid to the buyer's direct upline when this pack is redeemed (0 = none).
            $table->decimal('referral_bonus', 36, 6)->default(0)->after('extra_contacts');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            $table->dropColumn('referral_bonus');
        });
    }
};
