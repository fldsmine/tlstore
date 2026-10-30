<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum CouponType: string
{
    case ACTIVATION = 'activation';
    case SHARE_ACTIVATION = 'share_activation';
    case UPGRADE = 'upgrade';
    case EXTRA_MINUTES = 'extra_minutes';
    case PIN_RESET = 'pin_reset';
    case CLOUD = 'cloud';
    case CLOUD_RENEWAL = 'cloud_renewal';
    case WEBSITE_RENEWAL = 'website_renewal';
    case STORAGE_ACTIVATION = 'storage_activation';
    case LOAN_FEE = 'loan_fee';

    public function getName(): string
    {
        return match ($this) {
            self::LOAN_FEE => 'Loan Application Fee',
            default => Str::headline($this->value),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVATION => 'info',
            self::SHARE_ACTIVATION => 'purple',
            self::UPGRADE => 'blue',
            self::EXTRA_MINUTES => 'success',
            self::PIN_RESET => 'warning',
            self::CLOUD => 'purple',
            self::CLOUD_RENEWAL => 'purple',
            self::WEBSITE_RENEWAL => 'blue',
            self::STORAGE_ACTIVATION => 'success',
            self::LOAN_FEE => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
