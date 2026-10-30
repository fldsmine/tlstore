<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wallet reshape:
     *  - call_earnings_balance → shareholder_balance (renamed; the wallet is now Shareholder Earnings).
     *  - cloud_balance splits into cloud_call_balance + cloud_sms_balance; the existing balance was
     *    all sponsored-call earnings, so it moves wholesale into cloud_call_balance.
     *  - bills_balance is new: a spend-only wallet users top up to pay VTU/bills from.
     * Historical transaction rows carry the old wallet keys in `wallet`; they are remapped to match.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('call_earnings_balance', 'shareholder_balance');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('cloud_call_balance', 36, 6)->default(0)->after('agent_balance');
            $table->decimal('cloud_sms_balance', 36, 6)->default(0)->after('cloud_call_balance');
            $table->decimal('bills_balance', 36, 6)->default(0)->after('cloud_sms_balance');
        });

        DB::table('users')->update(['cloud_call_balance' => DB::raw('cloud_balance')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cloud_balance');
        });

        // Keep the ledger consistent with the new wallet keys.
        DB::table('transactions')->where('wallet', 'call_earnings')->update(['wallet' => 'shareholder']);
        DB::table('transactions')->where('wallet', 'cloud')->update(['wallet' => 'cloud_call']);
    }

    public function down(): void
    {
        DB::table('transactions')->where('wallet', 'shareholder')->update(['wallet' => 'call_earnings']);
        DB::table('transactions')->whereIn('wallet', ['cloud_call', 'cloud_sms'])->update(['wallet' => 'cloud']);

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('cloud_balance', 36, 6)->default(0)->after('agent_balance');
        });

        DB::table('users')->update(['cloud_balance' => DB::raw('cloud_call_balance + cloud_sms_balance')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cloud_call_balance', 'cloud_sms_balance', 'bills_balance']);
            $table->renameColumn('shareholder_balance', 'call_earnings_balance');
        });
    }
};
