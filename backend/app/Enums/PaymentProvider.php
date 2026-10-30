<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case KORAPAY = 'korapay';
    case PAYSTACK = 'paystack';
    case FLUTTERWAVE = 'flutterwave';
    case BACHS = 'bachs';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::KORAPAY => 'Korapay',
            self::PAYSTACK => 'Paystack',
            self::FLUTTERWAVE => 'Flutterwave',
            self::BACHS => 'Bachs',
            self::MANUAL => 'Manual bank transfer',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
