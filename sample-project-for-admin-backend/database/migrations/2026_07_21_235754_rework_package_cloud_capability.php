<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cloud capability moves from the Cloud plan onto the base package: Royal grants calls + SMS +
     * contacts, Diamond grants SMS only. The free trial is retired, and Cloud plans become contact
     * add-on packs bought like extra minutes (many per user, so they get their own table).
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'cloud_calls')) {
                $table->boolean('cloud_calls')->default(false)->after('enable_cloud');
            }
            if (! Schema::hasColumn('packages', 'cloud_sms')) {
                $table->boolean('cloud_sms')->default(false)->after('cloud_calls');
            }
            if (! Schema::hasColumn('packages', 'cloud_contact_limit')) {
                $table->unsignedInteger('cloud_contact_limit')->default(0)->after('cloud_sms');
            }
            if (! Schema::hasColumn('packages', 'cloud_daily_earning_cap')) {
                $table->decimal('cloud_daily_earning_cap', 36, 6)->default(0)->after('cloud_contact_limit');
            }
            if (! Schema::hasColumn('packages', 'cloud_hourly_earning_cap')) {
                $table->decimal('cloud_hourly_earning_cap', 36, 6)->default(0)->after('cloud_daily_earning_cap');
            }
            // Paid to the upline when a referee upgrades to this package.
            if (! Schema::hasColumn('packages', 'upgrade_referral_bonus')) {
                $table->decimal('upgrade_referral_bonus', 36, 6)->default(0)->after('indirect_referral_bonus');
            }
            // Admin-authored activation/upgrade success copy; blank falls back to the generic string.
            if (! Schema::hasColumn('packages', 'success_message')) {
                $table->text('success_message')->nullable()->after('features');
            }
        });

        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_storage_plans', 'extra_contacts')) {
                $table->unsignedInteger('extra_contacts')->default(0)->after('description');
            }
        });

        // Carry the old per-plan quota over as the pack's contact grant.
        if (Schema::hasColumn('cloud_storage_plans', 'number_quota')) {
            DB::table('cloud_storage_plans')->update(['extra_contacts' => DB::raw('number_quota')]);
        }

        if (! Schema::hasTable('cloud_storage_top_ups')) {
            Schema::create('cloud_storage_top_ups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('cloud_storage_plan_id')->nullable()->index();
                $table->uuid('coupon_id')->nullable()->index();
                $table->unsignedInteger('extra_contacts')->default(0);
                $table->timestamp('expires_at')->nullable()->index(); // null = never expires
                $table->timestamps();
            });
        }

        // SQLite rebuilds the table to drop a column and trips over the orphaned index — clear it first.
        if (Schema::hasColumn('users', 'cloud_storage_plan_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropIndex('users_cloud_storage_plan_id_index'));
        }

        // Retired: the trial, and the plan-level settings that now live on the package.
        $this->dropColumns('packages', ['grants_cloud_trial', 'cloud_trial_days']);
        $this->dropColumns('users', ['cloud_trial_used', 'cloud_storage_plan_id', 'cloud_plan_expires_at']);
        $this->dropColumns('cloud_storage_plans', ['is_trial', 'number_quota', 'max_earning', 'max_earning_per_hour']);
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_storage_top_ups');

        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'grants_cloud_trial')) {
                $table->boolean('grants_cloud_trial')->default(false);
            }
            if (! Schema::hasColumn('packages', 'cloud_trial_days')) {
                $table->unsignedInteger('cloud_trial_days')->default(0);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cloud_trial_used')) {
                $table->boolean('cloud_trial_used')->default(false);
            }
            if (! Schema::hasColumn('users', 'cloud_storage_plan_id')) {
                $table->uuid('cloud_storage_plan_id')->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'cloud_plan_expires_at')) {
                $table->timestamp('cloud_plan_expires_at')->nullable();
            }
        });

        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_storage_plans', 'is_trial')) {
                $table->boolean('is_trial')->default(false);
            }
            if (! Schema::hasColumn('cloud_storage_plans', 'number_quota')) {
                $table->unsignedInteger('number_quota')->default(0);
            }
            if (! Schema::hasColumn('cloud_storage_plans', 'max_earning')) {
                $table->decimal('max_earning', 18, 4)->default(0);
            }
            if (! Schema::hasColumn('cloud_storage_plans', 'max_earning_per_hour')) {
                $table->decimal('max_earning_per_hour', 18, 4)->default(0);
            }
        });

        $this->dropColumns('packages', ['cloud_calls', 'cloud_sms', 'cloud_contact_limit', 'cloud_daily_earning_cap', 'cloud_hourly_earning_cap', 'upgrade_referral_bonus', 'success_message']);
        $this->dropColumns('cloud_storage_plans', ['extra_contacts']);
    }

    /**
     * One column per statement — SQLite can't batch-drop.
     *
     * @param  array<int, string>  $columns
     */
    private function dropColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
