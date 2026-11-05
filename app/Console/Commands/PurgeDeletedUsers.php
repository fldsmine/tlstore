<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PurgeDeletedUsers extends Command
{
    protected $signature = 'users:purge-deleted {--days=30 : Minimum number of days an account must be soft-deleted}';

    protected $description = 'Permanently remove user accounts soft-deleted longer than the retention period.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $purged = 0;

        User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$purged): void {
                foreach ($users as $user) {
                    $media = [$user->avatar, $user->cover_image];
                    DB::transaction(function () use ($user): void {
                        $walletIds = DB::table('wallets')->where('user_id', $user->id)->pluck('id');
                        $referralCodeIds = DB::table('referral_codes')->where('user_id', $user->id)->pluck('id');

                        // Clear nullable audit/approval references and rows that restrict wallet deletion.
                        DB::table('testimonials')->where('approved_by', $user->id)->update(['approved_by' => null]);
                        if ($walletIds->isNotEmpty()) {
                            DB::table('referral_rewards')->whereIn('wallet_id', $walletIds)->delete();
                            DB::table('withdrawal_requests')->whereIn('wallet_id', $walletIds)->delete();
                        }
                        if ($referralCodeIds->isNotEmpty()) {
                            DB::table('referral_attributions')->whereIn('referral_code_id', $referralCodeIds)->delete();
                        }
                        DB::table('referral_attributions')
                            ->where('referrer_user_id', $user->id)
                            ->orWhere('referred_user_id', $user->id)
                            ->delete();
                        DB::table('referral_codes')->where('user_id', $user->id)->delete();

                        if ($walletIds->isNotEmpty()) {
                            DB::table('ledger_entries')->whereIn('wallet_id', $walletIds)->delete();
                            DB::table('wallets')->whereIn('id', $walletIds)->delete();
                        }

                        $user->tokens()->delete();
                        $user->forceDelete();
                    });

                    foreach ($media as $path) {
                        if ($path && ! preg_match('#^https?://#i', $path)) {
                            Storage::disk('public')->delete(preg_replace('#^/?storage/#', '', $path));
                        }
                    }
                    $purged++;
                }
            });

        $this->info("Permanently deleted {$purged} account(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
