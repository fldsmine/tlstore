<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign vendors mint foreign codes and are billed a separate price pair. Nullable so a blank
     * value falls back to the Nigerian pair (see App\Support\RegionPricing) rather than selling at 0.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'foreign_price')) {
                $table->decimal('foreign_price', 36, 6)->nullable()->after('agent_price');
            }
            if (! Schema::hasColumn('packages', 'foreign_agent_price')) {
                $table->decimal('foreign_agent_price', 36, 6)->nullable()->after('foreign_price');
            }
        });

        Schema::table('additional_minute_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('additional_minute_plans', 'foreign_price')) {
                $table->decimal('foreign_price', 36, 6)->nullable()->after('agent_price');
            }
            if (! Schema::hasColumn('additional_minute_plans', 'foreign_agent_price')) {
                $table->decimal('foreign_agent_price', 36, 6)->nullable()->after('foreign_price');
            }
        });

        // Cloud plans price in 4dp (token_price/agent_price) — mirror that precision.
        Schema::table('cloud_storage_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('cloud_storage_plans', 'foreign_token_price')) {
                $table->decimal('foreign_token_price', 36, 6)->nullable()->after('agent_price');
            }
            if (! Schema::hasColumn('cloud_storage_plans', 'foreign_agent_price')) {
                $table->decimal('foreign_agent_price', 36, 6)->nullable()->after('foreign_token_price');
            }
        });
    }

    public function down(): void
    {
        // One column per statement — SQLite can't batch-drop.
        foreach (['packages', 'additional_minute_plans'] as $table) {
            foreach (['foreign_price', 'foreign_agent_price'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }

        foreach (['foreign_token_price', 'foreign_agent_price'] as $column) {
            if (Schema::hasColumn('cloud_storage_plans', $column)) {
                Schema::table('cloud_storage_plans', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
