<?php

namespace App\Enums;

enum ChatMessageSender: string
{
    case CUSTOMER = 'customer';
    case AGENT = 'agent';
    case SYSTEM = 'system';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
