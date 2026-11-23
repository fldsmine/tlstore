<?php

namespace App\Enums;

enum PayLaterStatus: string
{
    case ACTIVE = 'active';
    case REPAID = 'repaid';
    case DEFAULTED = 'defaulted';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::REPAID => 'Repaid',
            self::DEFAULTED => 'Defaulted',
        };
    }
}
