<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('cloud_storage_plans', 'tier_level')) {
                $table->unsignedInteger('tier_level')->nullable()->after('id');
            }

            if (!Schema::hasColumn('cloud_storage_plans', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('tier_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (Schema::hasColumn('cloud_storage_plans', 'tier_level')) {
                $table->dropColumn('tier_level');
            }

            if (Schema::hasColumn('cloud_storage_plans', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
