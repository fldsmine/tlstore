<?php

namespace App\Enums;

enum LoanStatus: string
{
    case PROCESSING = 'processing';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PROCESSING => 'Pending review',
            self::APPROVED => 'Approved',
            self::PAID => 'Paid / disbursed',
            self::REJECTED => 'Rejected',
        };
    }
}
