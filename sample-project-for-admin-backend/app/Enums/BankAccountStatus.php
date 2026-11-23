<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum BankAccountStatus: string
{
    case VERIFIED = 'verified';
    case PENDING = 'pending';

    public function getName(): string
    {
        return Str::headline($this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
