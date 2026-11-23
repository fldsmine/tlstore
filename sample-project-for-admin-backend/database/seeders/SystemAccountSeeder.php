<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\User;
use App\Services\PackageActivationService;
use Illuminate\Database\Seeder;

class SystemAccountSeeder extends Seeder
{
    public function run(): void
    {
        $package = Package::query()->orderByDesc('tier_level')->first();

        if (! $package) {
            $this->command?->warn('SystemAccountSeeder: no package found — run PackageSeeder first.');

            return;
        }

        $password = (string) config('app.system_account_password');

        $accounts = [
            ['username' => 'system1', 'email' => 'system1@nextelconnect.com', 'last_name' => 'One', 'country' => 'NG'],
            ['username' => 'system2', 'email' => 'system2@nextelconnect.com', 'last_name' => 'Two', 'country' => 'GH'],
        ];

        foreach ($accounts as $account) {
            if (User::query()->where('email', $account['email'])->exists()) {
                continue;
            }

            $user = User::factory()->create([
                'first_name' => 'System',
                'last_name' => $account['last_name'],
                'username' => $account['username'],
                'email' => $account['email'],
                'country' => $account['country'],
                'is_agent' => true,
                'show_on_agents_page' => false,
                'is_special' => true,
                'transaction_pin' => '1234',
                'password' => $password,
                'withdrawals_disabled' => true,
                'package_id' => null,
                'email_verified_at' => now(),
            ]);

            app(PackageActivationService::class)->activate($user, $package);
        }
    }
}
