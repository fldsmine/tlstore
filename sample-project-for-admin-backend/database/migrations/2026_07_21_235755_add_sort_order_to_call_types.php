<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shareholder cards were ordered by rate, which is volatile — an admin rate tweak silently
     * reshuffled the grid. An explicit order makes the arrangement intentional and editable.
     */
    public function up(): void
    {
        Schema::table('call_types', function (Blueprint $table) {
            if (! Schema::hasColumn('call_types', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('claimable_percentage');
            }
        });

        // On-Net leads (it is the everyday earner); International moves to where On-Net sat.
        foreach (['on_net' => 0, 'off_net' => 1, 'broadband' => 2, 'international' => 3] as $key => $order) {
            DB::table('call_types')->where('key', $key)->update(['sort_order' => $order]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('call_types', 'sort_order')) {
            Schema::table('call_types', fn (Blueprint $t) => $t->dropColumn('sort_order'));
        }
    }
};
