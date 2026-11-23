<?php

namespace App\Livewire\Concerns;

use App\Support\TransactionPinGuard;

trait VerifiesTransactionPin
{
    protected function verifyTransactionPin(string $pin, string $field = 'pin'): bool
    {
        $user = auth()->user();
        $guard = app(TransactionPinGuard::class);

        if ($guard->throttled($user)) {
            $this->addError($field, 'Too many incorrect attempts. Try again in '.$guard->availableIn($user).' seconds.');

            return false;
        }

        if (! $guard->verify($user, $pin)) {
            $this->addError($field, 'Your transaction PIN is incorrect.');

            return false;
        }

        return true;
    }
}
