<?php

namespace App\Application\Security;

use Illuminate\Support\Facades\Hash;

final class SecurityPinHasher
{
    public function make(string $pin): string
    {
        return Hash::make($this->peppered($pin));
    }

    public function check(string $pin, ?string $storedHash): bool
    {
        return is_string($storedHash) && $storedHash !== '' && Hash::check($this->peppered($pin), $storedHash);
    }

    private function peppered(string $pin): string
    {
        return hash_hmac('sha256', $pin, (string) config('app.key'));
    }
}
