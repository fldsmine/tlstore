<?php

namespace App\Enums;

enum CloudCallStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case MISSED = 'missed';
    case DECLINED = 'declined';
    case ENDED = 'ended';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::ACTIVE => 'blue',
            self::COMPLETED => 'success',
            self::MISSED => 'warning',
            self::DECLINED => 'danger',
            self::ENDED => 'muted',
        };
    }
}
