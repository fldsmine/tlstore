<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The USD/local toggle was per-component Alpine state, so it reset on every page load and only
     * affected the one balance card. Persisting it makes it a real account-wide display preference.
     * Null = USD (the storage currency); otherwise an ISO code such as NGN.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'display_currency')) {
                $table->string('display_currency', 3)->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'display_currency')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('display_currency'));
        }
    }
};
