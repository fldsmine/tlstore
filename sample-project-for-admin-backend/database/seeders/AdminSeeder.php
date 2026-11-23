<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('app.system_account_password');

        $admin = Admin::query()->firstOrNew(['email' => 'super@nextelconnect.com']);
        $admin->name = 'Super Admin';
        $admin->role = AdminRole::SUPER->value;

        if (! $admin->exists) {
            $admin->password = $password;
        }

        $admin->save();
    }
}
