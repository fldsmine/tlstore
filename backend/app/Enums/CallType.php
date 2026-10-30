<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum CallType: string
{
    case ON_NET = 'on_net';
    case OFF_NET = 'off_net';
    case INTERNATIONAL = 'international';
    case BROADBAND = 'broadband';

    public function getName(): string
    {
        return Str::headline($this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
