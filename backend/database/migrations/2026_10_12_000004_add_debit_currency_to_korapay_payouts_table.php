<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The backoffice "Send money" flow is shared by every payment manager and writes ONE audit shape,
 * so both payout tables carry the same columns. Korapay pays out in a single currency (the picker
 * stays hidden for it), so this column stays null there — it exists so the shared component can
 * insert the same row whatever manager is active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('korapay_payouts', function (Blueprint $table) {
            $table->string('debit_currency', 8)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('korapay_payouts', function (Blueprint $table) {
            $table->dropColumn('debit_currency');
        });
    }
};
