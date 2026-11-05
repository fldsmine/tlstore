<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->decimal('subscription_referral_percent', 5, 2)->default(5)->comment('Referral reward percentage of the subscription amount paid');
            $table->decimal('task_referral_percent', 5, 2)->default(3)->comment('Referral reward percentage of an approved task reward');
        });

        DB::table('system_settings')->insertOrIgnore([
            ['setting_key' => 'rewards.referral_subscription_active', 'value' => json_encode(true), 'is_public' => false, 'created_at' => now(), 'updated_at' => now()],
            ['setting_key' => 'rewards.referral_task_active', 'value' => json_encode(true), 'is_public' => false, 'created_at' => now(), 'updated_at' => now()],
            ['setting_key' => 'rewards.subscription_bonus_active', 'value' => json_encode(true), 'is_public' => false, 'created_at' => now(), 'updated_at' => now()],
            ['setting_key' => 'rewards.social_follow_active', 'value' => json_encode(true), 'is_public' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        if (Schema::hasTable('user_social_follows')) {
            DB::table('user_social_follows')->select(['user_id', 'currency'])->distinct()->orderBy('user_id')->get()
                ->each(function (object $row): void {
                    $now = now();
                    DB::table('wallets')->insertOrIgnore([
                        'user_id' => $row->user_id, 'wallet_type' => 'bonus', 'currency' => $row->currency,
                        'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $bonusWalletId = DB::table('wallets')->where('user_id', $row->user_id)->where('wallet_type', 'bonus')->where('currency', $row->currency)->value('id');
                    $followIds = DB::table('user_social_follows')->where('user_id', $row->user_id)->where('currency', $row->currency)->whereNotNull('ledger_entry_id')->pluck('ledger_entry_id');
                    if ($followIds->isNotEmpty()) {
                        DB::table('ledger_entries')->whereIn('id', $followIds)->update(['wallet_id' => $bonusWalletId, 'updated_at' => $now]);
                        if (Schema::hasTable('transactions')) {
                            foreach (DB::table('user_social_follows')->whereIn('ledger_entry_id', $followIds)->get(['user_id', 'social_handle_id']) as $follow) {
                                DB::table('transactions')->where('reference', 'SOCIAL-'.$follow->user_id.'-'.$follow->social_handle_id)->where('type', 'social_follow_reward')->delete();
                            }
                        }
                    }
                });
        }

        if (Schema::hasTable('users')) {
            DB::table('users')->orderBy('id')->chunk(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('wallets')->insertOrIgnore([
                        'user_id' => $user->id, 'wallet_type' => 'bonus', 'currency' => $user->preferred_currency ?: 'USD',
                        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('referral_rewards', function (Blueprint $table): void {
            $table->dropUnique('referral_reward_source_unique');
            $table->dropColumn(['reward_type', 'source_type', 'source_id']);
            $table->unique(['referral_attribution_id', 'wallet_id']);
        });
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn(['subscription_referral_percent', 'task_referral_percent']);
        });
        DB::table('system_settings')->whereIn('setting_key', [
            'rewards.referral_subscription_active', 'rewards.referral_task_active',
            'rewards.subscription_bonus_active', 'rewards.social_follow_active',
        ])->delete();
        DB::table('wallets')->where('wallet_type', 'bonus')->delete();
    }
};
