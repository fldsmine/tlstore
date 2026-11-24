<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code')->nullable()->comment('auto set from referral_codes table')->after('has_addon');
            $table->integer('is_member')->default(1)->comment('0=is not, 1 has subscription')->after('referral_code');
        });

        // Populate users.my_code with existing referral codes.
        DB::table('referral_codes')
            ->select('user_id', 'code')
            ->orderBy('id')
            ->chunk(500, function ($referralCodes) {
                foreach ($referralCodes as $referral) {
                    DB::table('users')
                        ->where('id', $referral->user_id)
                        ->update([
                            'referral_code' => $referral->code,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_code');
        });
    }
};
