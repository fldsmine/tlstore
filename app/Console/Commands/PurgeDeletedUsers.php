<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class PurgeDeletedUsers extends Command
{
    protected $signature = 'users:purge-deleted
        {--days=30 : Minimum number of days an account must be soft-deleted}';

    protected $description = 'Permanently remove user accounts soft-deleted longer than the retention period.';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $report = [
            'started_at' => now()->toDateTimeString(),
            'purged' => 0,
            'failed' => 0,
            'media_deleted' => 0,
            'media_failed' => 0,
            'users' => [],
            'failures' => [],
        ];

        User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$report): void {
                foreach ($users as $user) {
                    $media = [$user->avatar, $user->cover_image];

                    try {
                        DB::transaction(function () use ($user): void {
                            $walletIds = DB::table('wallets')
                                ->where('user_id', $user->id)
                                ->pluck('id');

                            $referralCodeIds = DB::table('referral_codes')
                                ->where('user_id', $user->id)
                                ->pluck('id');

                            DB::table('testimonials')
                                ->where('approved_by', $user->id)
                                ->update(['approved_by' => null]);

                            if ($walletIds->isNotEmpty()) {
                                DB::table('referral_rewards')
                                    ->whereIn('wallet_id', $walletIds)
                                    ->delete();

                                DB::table('withdrawal_requests')
                                    ->whereIn('wallet_id', $walletIds)
                                    ->delete();
                            }

                            if ($referralCodeIds->isNotEmpty()) {
                                DB::table('referral_attributions')
                                    ->whereIn('referral_code_id', $referralCodeIds)
                                    ->delete();
                            }

                            DB::table('referral_attributions')
                                ->where('referrer_user_id', $user->id)
                                ->orWhere('referred_user_id', $user->id)
                                ->delete();

                            DB::table('referral_codes')
                                ->where('user_id', $user->id)
                                ->delete();

                            if ($walletIds->isNotEmpty()) {
                                DB::table('ledger_entries')
                                    ->whereIn('wallet_id', $walletIds)
                                    ->delete();

                                DB::table('wallets')
                                    ->whereIn('id', $walletIds)
                                    ->delete();
                            }

                            $user->tokens()->delete();
                            $user->forceDelete();
                        });

                        $report['purged']++;

                        $report['users'][] = [
                            'id' => $user->id,
                            'email' => $user->email,
                            'deleted_at' => $user->deleted_at?->toDateTimeString(),
                            'status' => 'purged',
                        ];

                        foreach ($media as $path) {
                            if (! $path || preg_match('#^https?://#i', $path)) {
                                continue;
                            }

                            try {
                                $deleted = Storage::disk('public')->delete(
                                    preg_replace('#^/?storage/#', '', $path)
                                );

                                if ($deleted) {
                                    $report['media_deleted']++;
                                } else {
                                    $report['media_failed']++;

                                    $report['failures'][] = [
                                        'user_id' => $user->id,
                                        'type' => 'media',
                                        'path' => $path,
                                        'error' => 'Storage deletion returned false.',
                                    ];
                                }
                            } catch (Throwable $e) {
                                $report['media_failed']++;

                                $report['failures'][] = [
                                    'user_id' => $user->id,
                                    'type' => 'media',
                                    'path' => $path,
                                    'error' => $e->getMessage(),
                                ];
                            }
                        }
                    } catch (Throwable $e) {
                        $report['failed']++;

                        $report['failures'][] = [
                            'user_id' => $user->id,
                            'type' => 'user_purge',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            });

        Log::channel('purge')->info('Users purge report', $report);

        $report['execution_seconds'] = round(microtime(true) - $startedAt, 2);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Successfully purged', $report['purged']],
                ['Failed user deletions', $report['failed']],
                ['Media files deleted', $report['media_deleted']],
                ['Media deletion failures', $report['media_failed']],
                ['Execution time (seconds)', $report['execution_seconds']],
            ]
        );

        $this->info('Purge report saved successfully.');

        return $report['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
