<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ReferralStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';

    public function getName(): string
    {
        return Str::headline($this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
