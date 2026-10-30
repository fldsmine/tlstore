<?php

namespace App\Enums;

enum LoanType: string
{
    case STUDENT = 'student';
    case BUSINESS = 'business';
    case AGRO_FARM = 'agro_farm';
    case PERSONAL = 'personal';
    case TRAVEL = 'travel';
    case TRADE = 'trade';

    public function label(): string
    {
        return match ($this) {
            self::STUDENT => 'Student Loan',
            self::BUSINESS => 'Business Loan',
            self::AGRO_FARM => 'Agro Farm Loan',
            self::PERSONAL => 'Personal Loan',
            self::TRAVEL => 'Travel Loan',
            self::TRADE => 'Trading Loan',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
