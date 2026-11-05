<?php

namespace App\Exceptions;

use RuntimeException;

final class AccountUnavailable extends RuntimeException
{
    public function __construct(public readonly string $state, string $reason)
    {
        parent::__construct($reason);
    }
}
