<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function getName(): string
    {
        return Str::headline($this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
