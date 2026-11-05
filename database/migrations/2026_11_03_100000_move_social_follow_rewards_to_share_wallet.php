<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_social_follows')) {
            return;
        }

        DB::table('user_social_follows')
            ->whereNotNull('ledger_entry_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'social_handle_id', 'currency', 'ledger_entry_id'])
            ->each(function (object $follow): void {
                $now = now();
                $shareWallet = DB::table('wallets')
                    ->where('user_id', $follow->user_id)
                    ->where('wallet_type', 'share')
                    ->where('currency', $follow->currency)
                    ->first(['id']);

                if (! $shareWallet) {
                    $shareWalletId = DB::table('wallets')->insertGetId([
                        'user_id' => $follow->user_id,
                        'wallet_type' => 'share',
                        'currency' => $follow->currency,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $shareWalletId = $shareWallet->id;
                }

                DB::table('ledger_entries')
                    ->where('id', $follow->ledger_entry_id)
                    ->update(['wallet_id' => $shareWalletId, 'updated_at' => $now]);
                if (Schema::hasTable('transactions')) {
                    DB::table('transactions')
                        ->where('reference', 'SOCIAL-'.$follow->user_id.'-'.$follow->social_handle_id)
                        ->where('type', 'social_follow_reward')
                        ->update(['wallet' => 'share', 'updated_at' => $now]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_social_follows')) {
            return;
        }

        DB::table('user_social_follows')
            ->whereNotNull('ledger_entry_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'social_handle_id', 'currency', 'ledger_entry_id'])
            ->each(function (object $follow): void {
                $now = now();
                $referralWallet = DB::table('wallets')
                    ->where('user_id', $follow->user_id)
                    ->where('wallet_type', 'referral')
                    ->where('currency', $follow->currency)
                    ->first(['id']);

                if (! $referralWallet) {
                    $referralWalletId = DB::table('wallets')->insertGetId([
                        'user_id' => $follow->user_id,
                        'wallet_type' => 'referral',
                        'currency' => $follow->currency,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $referralWalletId = $referralWallet->id;
                }

                DB::table('ledger_entries')
                    ->where('id', $follow->ledger_entry_id)
                    ->update(['wallet_id' => $referralWalletId, 'updated_at' => $now]);
                if (Schema::hasTable('transactions')) {
                    DB::table('transactions')
                        ->where('reference', 'SOCIAL-'.$follow->user_id.'-'.$follow->social_handle_id)
                        ->where('type', 'social_follow_reward')
                        ->update(['wallet' => 'referral', 'updated_at' => $now]);
                }
            });
    }
};
