<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Str;

trait ProvidesDemoAccountPassword
{
    /**
     * Resolve the password used by the demo seeders.
     *
     * Demo accounts must never be created with a guessable password. The value
     * comes from SYSTEM_ACCOUNT_PASSWORD; when it is missing or weak we either
     * generate a one-off password (local/testing) or refuse to seed demo
     * accounts at all (every other environment).
     */
    protected function demoAccountPassword(): ?string
    {
        $password = trim((string) config('app.system_account_password'));

        if ($this->isAcceptableDemoPassword($password)) {
            return $password;
        }

        if (app()->environment(['local', 'testing'])) {
            $generated = Str::password(24);

            $this->command?->warn(
                'SYSTEM_ACCOUNT_PASSWORD is missing or too weak; using a one-off demo password: '.$generated
            );

            return $generated;
        }

        $this->command?->warn(
            'SYSTEM_ACCOUNT_PASSWORD is missing or too weak; skipping demo account seeding. '
            .'Set a strong SYSTEM_ACCOUNT_PASSWORD to seed demo accounts.'
        );

        return null;
    }

    private function isAcceptableDemoPassword(string $password): bool
    {
        if (mb_strlen($password) < 12) {
            return false;
        }

        return ! in_array(mb_strtolower($password), [
            'password123!',
            'meandmyson',
            'adminadmin12',
            'changeme1234',
            'letmein12345',
            'qwertyuiop12',
            '123456789012',
        ], true);
    }
}
