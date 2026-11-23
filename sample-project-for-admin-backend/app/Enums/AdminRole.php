<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum AdminRole: string
{
    case SUPER = 'super';
    case REGULAR = 'regular';
    case SUPPORT = 'support';

    public function getName(): string
    {
        return Str::headline($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::SUPER => 'warning',
            self::REGULAR => 'info',
            self::SUPPORT => 'muted',
        };
    }

    public function canWrite(): bool
    {
        return $this !== self::SUPPORT;
    }

    public function canManageAdmins(): bool
    {
        return $this === self::SUPER;
    }

    public static function assignable(): array
    {
        return [self::REGULAR, self::SUPPORT];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
