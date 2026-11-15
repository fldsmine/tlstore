<?php

namespace Database\Seeders;

use App\Application\Actions\SocialFollow\ClaimSocialFollowReward;
use App\Models\SocialHandle;
use App\Models\User;
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

                app(ClaimSocialFollowReward::class)->handle($user, $handle);

            }
        });
    }
}
