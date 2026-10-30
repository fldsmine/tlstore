<?php

namespace App\Enums;

enum RewardTargetStatus: string
{
    case ACTIVE = 'active';
    case ACHIEVED = 'achieved';
    case CLAIMED = 'claimed';
    case MISSED = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'In progress',
            self::ACHIEVED => 'Unlocked',
            self::CLAIMED => 'Claimed',
            self::MISSED => 'Missed target',
        };
    }
}
