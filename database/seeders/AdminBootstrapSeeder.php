<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Secure, explicit bootstrap for the FIRST (or rescue) super-admin account.
 *
 * Credentials come from the environment and are never printed:
 *   ADMIN_BOOTSTRAP_EMAIL     admin@example.com
 *   ADMIN_BOOTSTRAP_PASSWORD  (min 12 chars; required)
 *   ADMIN_BOOTSTRAP_NAME      (optional, defaults to "Administrator")
 *
 * Usage:
 *   ADMIN_BOOTSTRAP_EMAIL=admin@example.com ADMIN_BOOTSTRAP_PASSWORD='...' \
 *     php artisan db:seed --class=AdminBootstrapSeeder
 *
 * Idempotent: an existing admin with the same email gets a password RESET and its
 * role forced to super_admin (this is a rescue path — treat it like one), and
 * every run writes an `admin.bootstrap_upserted` audit row. There is no default
 * password anywhere in the repository.
 */
class AdminBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('ADMIN_BOOTSTRAP_EMAIL', '')));
        $password = (string) env('ADMIN_BOOTSTRAP_PASSWORD', '');
        $name = trim((string) env('ADMIN_BOOTSTRAP_NAME', 'Administrator')) ?: 'Administrator';

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command?->error('ADMIN_BOOTSTRAP_EMAIL must be set to a valid email address.');

            return;
        }
        if (mb_strlen($password) < 12) {
            $this->command?->error('ADMIN_BOOTSTRAP_PASSWORD must be at least 12 characters.');

            return;
        }

        $admin = Admin::query()->where('email', $email)->first();
        $existed = (bool) $admin;
        $admin ??= new Admin;
        $admin->forceFill([
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'status' => 'active',
        ])->save();

        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => 'admin.bootstrap_upserted',
            'entity_type' => 'admin',
            'entity_id' => (string) $admin->id,
            'after_values' => ['email' => $email, 'rescue_reset' => $existed],
            'created_at' => now(),
        ]);

        $this->command?->info($existed
            ? "Super admin password reset for {$email} (rescue path). Change it after signing in."
            : "Super admin {$email} created. Change the password after first sign-in.");
    }
}
