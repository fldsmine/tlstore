<?php

namespace Database\Seeders;

use App\Models\LedgerEntry;
use App\Models\SocialHandle;
use App\Models\User;
use App\Models\UserSocialFollow;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialFollowSeeder extends Seeder
{
    public function run(): void
    {
        $demoFollows = [
            ['user' => 1, 'handle' => 'facebook'],
            ['user' => 1, 'handle' => 'telegram'],
            ['user' => 2, 'handle' => 'instagram'],
            ['user' => 3, 'handle' => 'facebook'],
            ['user' => 3, 'handle' => 'youtube'],
        ];

        DB::transaction(function () use ($demoFollows): void {
            foreach ($demoFollows as $record) {
                $user = User::query()->where('email', sprintf('demo.user%02d@tlstore.test', $record['user']))->first();
                $handle = SocialHandle::query()->where('handle_key', $record['handle'])->first();
                if (! $user || ! $handle) {
                    continue;
                }

                $wallet = Wallet::query()->firstOrCreate(
                    ['user_id' => $user->id, 'wallet_type' => 'bonus', 'currency' => $handle->currency],
                    ['status' => 'active'],
                );
                $follow = UserSocialFollow::query()->firstOrCreate(
                    ['user_id' => $user->id, 'social_handle_id' => $handle->id],
                    [
                        'status' => 'completed',
                        'reward_amount_minor' => $handle->reward_amount_minor,
                        'currency' => $handle->currency,
                        'followed_at' => now()->subDays($record['user']),
                    ],
                );

                $ledgerEntry = LedgerEntry::query()->firstOrCreate(
                    ['idempotency_key' => 'social-follow:'.$user->id.':'.$handle->id],
                    [
                        'wallet_id' => $wallet->id,
                        'entry_type' => 'social_follow_reward',
                        'direction' => 'credit',
                        'amount_minor' => $handle->reward_amount_minor,
                        'status' => 'posted',
                        'source_type' => 'user_social_follow',
                        'source_id' => $follow->id,
                        'description' => 'Social follow reward: '.$handle->name,
                        'available_at' => $follow->followed_at,
                        'posted_at' => $follow->followed_at,
                    ],
                );
                if ($follow->ledger_entry_id !== $ledgerEntry->id) {
                    $follow->forceFill(['ledger_entry_id' => $ledgerEntry->id])->save();
                }

            }
        });
    }
}
