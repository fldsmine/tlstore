<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsor availability used to be derived from which storage plans a number was ticked into,
     * gated by those plans' tiers. That conflated storage (how many contacts you may keep) with
     * access (which sponsors you may call), and meant every new plan had to be re-ticked on every
     * number. Neither tiering nor the join earns its keep: any user whose package allows Cloud
     * calling can call any active sponsor. Both are removed.
     */
    public function up(): void
    {
        Schema::dropIfExists('cloud_number_plan');

        if (Schema::hasColumn('cloud_storage_plans', 'tier_level')) {
            Schema::table('cloud_storage_plans', fn (Blueprint $t) => $t->dropColumn('tier_level'));
        }

        // Added briefly while access was still tier-gated; sponsors are open to all Cloud callers now.
        if (Schema::hasColumn('cloud_numbers', 'tier_level')) {
            Schema::table('cloud_numbers', fn (Blueprint $t) => $t->dropColumn('tier_level'));
        }
    }

    public function down(): void
    {
        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_storage_plans', 'tier_level')) {
                $table->unsignedInteger('tier_level')->default(0);
            }
        });

        if (! Schema::hasTable('cloud_number_plan')) {
            Schema::create('cloud_number_plan', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('cloud_number_id')->constrained('cloud_numbers')->cascadeOnDelete();
                $table->foreignUuid('cloud_storage_plan_id')->constrained('cloud_storage_plans')->cascadeOnDelete();
            });
        }
    }
};
