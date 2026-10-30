<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vtu_orders', function (Blueprint $table) {
            $table->string('provider')->default('vtpass')->after('service_id');   // fulfilment provider; requery routes back to it
        });

        DB::table('vtu_orders')->whereNull('provider')->update(['provider' => 'vtpass']);
    }

    public function down(): void
    {
        Schema::table('vtu_orders', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
