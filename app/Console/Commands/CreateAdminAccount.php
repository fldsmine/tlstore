<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminAccount extends Command
{
    protected $signature = 'admin:create {email?} {--name=} {--role=super_admin}';

    protected $description = 'Create a secure browser admin account';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Admin email'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Admin full name')));
        $role = (string) $this->option('role');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            $this->components->error('Provide a valid email and non-empty name.');

            return self::FAILURE;
        }

        if (Admin::query()->where('email', $email)->exists()) {
            $this->components->error('An admin already uses that email. Use the admin management screen to edit it.');

            return self::FAILURE;
        }

        $password = $this->secret('Password (minimum 14 characters)');
        if (strlen((string) $password) < 14) {
            $this->components->error('The password must contain at least 14 characters.');

            return self::FAILURE;
        }

        $admin = Admin::query()->create([
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => in_array($role, ['super_admin', 'admin', 'content_admin', 'moderator', 'finance'], true)
                ? $role
                : 'admin',
            'status' => 'active',
        ]);

        $this->components->info("Admin {$admin->email} created. Credentials were not printed or emailed.");

        return self::SUCCESS;
    }
}
