<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NCloud plans gain a tier (earn-from-your-tier-or-lower), a trial flag, and a
        // monthly-renewal toggle (admin-visible now, billing wired later).
        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_storage_plans', 'tier_level')) {
                $table->unsignedInteger('tier_level')->default(0)->after('duration_days');
            }
            if (! Schema::hasColumn('cloud_storage_plans', 'is_trial')) {
                $table->boolean('is_trial')->default(false)->after('tier_level');
            }
        });

        // A base Nextel package can grant a Cloud trial on activation (per-package, configurable).
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'grants_cloud_trial')) {
                $table->boolean('grants_cloud_trial')->default(false)->after('tier_level');
            }
            if (! Schema::hasColumn('packages', 'cloud_trial_days')) {
                $table->unsignedInteger('cloud_trial_days')->default(0)->after('grants_cloud_trial');
            }
        });

        // Every Cloud user gets a virtual line (+1 (698) XXX-XXXX), auto-assigned on first access,
        // and a flag so the one-time trial is only ever granted once.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cloud_line_number')) {
                $table->string('cloud_line_number')->nullable()->unique()->after('cloud_plan_expires_at');
            }
            if (! Schema::hasColumn('users', 'cloud_trial_used')) {
                $table->boolean('cloud_trial_used')->default(false)->after('cloud_line_number');
            }
        });
    }

    public function down(): void
    {
        // Drop one column per statement — SQLite can't batch-drop.
        foreach (['tier_level', 'is_trial'] as $col) {
            if (Schema::hasColumn('cloud_storage_plans', $col)) {
                Schema::table('cloud_storage_plans', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
        foreach (['grants_cloud_trial', 'cloud_trial_days'] as $col) {
            if (Schema::hasColumn('packages', $col)) {
                Schema::table('packages', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
        foreach (['cloud_line_number', 'cloud_trial_used'] as $col) {
            if (Schema::hasColumn('users', $col)) {
                Schema::table('users', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
