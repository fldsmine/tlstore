<?php

namespace App\Enums;

enum VtuOrderStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Reversed = 'reversed';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
